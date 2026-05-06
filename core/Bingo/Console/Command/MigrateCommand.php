<?php

declare(strict_types = 1);

namespace Bingo\Console\Command;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MigrateCommand extends Command
{
    private const string TABLE = 'migrations';

    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('db:migrate')
            ->setAliases(['db:m'])
            ->setDescription('Run pending migrations, rollback the last batch, or rebuild the schema from scratch')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Rollback the last batch (or N batches with --step)')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Number of batches to rollback', '1')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Drop all tables and re-run every migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->basePath . '/database/migrations';

        if (!is_dir($dir)) {
            $output->writeln('<error>No migrations directory found at database/migrations</error>');
            return Command::FAILURE;
        }

        $rollback = (bool) $input->getOption('rollback');
        $fresh    = (bool) $input->getOption('fresh');

        if ($rollback && $fresh) {
            $output->writeln('<error>--rollback and --fresh are mutually exclusive</error>');
            return Command::FAILURE;
        }

        if ($fresh) {
            return $this->runFresh($dir, $output);
        }

        $this->ensureMigrationsTable();

        if ($rollback) {
            $steps = max(1, (int) $input->getOption('step'));
            return $this->runRollback($dir, $output, $steps);
        }

        return $this->runUp($dir, $output);
    }

    private function runUp(string $dir, OutputInterface $output): int
    {
        $files   = $this->discoverFiles($dir);
        $applied = $this->appliedMigrations();
        $pending = array_values(array_filter(
            $files,
            static fn(string $f) => !in_array(basename($f, '.php'), $applied, true),
        ));

        if ($pending === []) {
            $output->writeln('<comment>Nothing to migrate.</comment>');
            return Command::SUCCESS;
        }

        $batch = $this->nextBatch();

        foreach ($pending as $file) {
            $name = basename($file, '.php');

            try {
                $this->loadMigration($file)->up();
            } catch (Throwable $e) {
                $output->writeln("<error>FAIL</error>     {$name}");
                $output->writeln('  <error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            Capsule::table(self::TABLE)->insert([
                'migration'   => $name,
                'batch'       => $batch,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);

            $output->writeln("<info>Migrated</info> {$name}");
        }

        $output->writeln('');
        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }

    private function runRollback(string $dir, OutputInterface $output, int $steps): int
    {
        $maxBatch = (int) Capsule::table(self::TABLE)->max('batch');

        if ($maxBatch === 0) {
            $output->writeln('<comment>Nothing to rollback.</comment>');
            return Command::SUCCESS;
        }

        $minBatch = max(1, $maxBatch - $steps + 1);

        $rows = Capsule::table(self::TABLE)
            ->where('batch', '>=', $minBatch)
            ->orderBy('batch', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($rows as $row) {
            $name = $row->migration;
            $file = $dir . '/' . $name . '.php';

            if (!is_file($file)) {
                $output->writeln("<comment>Skipped</comment>  {$name} (file missing)");
                continue;
            }

            try {
                $this->loadMigration($file)->down();
            } catch (Throwable $e) {
                $output->writeln("<error>FAIL</error>     {$name}");
                $output->writeln('  <error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            Capsule::table(self::TABLE)->where('id', $row->id)->delete();
            $output->writeln("<info>Reverted</info> {$name}");
        }

        $output->writeln('');
        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }

    private function runFresh(string $dir, OutputInterface $output): int
    {
        $output->writeln('<comment>Dropping all tables...</comment>');
        Capsule::schema()->dropAllTables();
        $this->ensureMigrationsTable();
        return $this->runUp($dir, $output);
    }

    private function ensureMigrationsTable(): void
    {
        $schema = Capsule::schema();
        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $schema->create(self::TABLE, static function (Blueprint $table) {
            $table->id();
            $table->string('migration')->unique();
            $table->unsignedInteger('batch')->index();
            $table->timestamp('executed_at')->useCurrent();
        });
    }

    /** @return string[] sorted absolute paths */
    private function discoverFiles(string $dir): array
    {
        $files = glob($dir . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    /** @return string[] migration names (filename without .php) */
    private function appliedMigrations(): array
    {
        return Capsule::table(self::TABLE)->pluck('migration')->all();
    }

    private function nextBatch(): int
    {
        return (int) Capsule::table(self::TABLE)->max('batch') + 1;
    }

    private function loadMigration(string $file): object
    {
        $migration = require $file;

        if (!is_object($migration) || !method_exists($migration, 'up') || !method_exists($migration, 'down')) {
            throw new RuntimeException(sprintf(
                'Migration %s must return an object with up() and down() methods.',
                basename($file),
            ));
        }

        return $migration;
    }
}
