<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateMigrationCommand extends Command
{
    public function __construct(private readonly string $basePath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate:migration')
            ->setAliases(['g:migration'])
            ->setDescription('Generate a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name (e.g. create_posts_table)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name      = $this->toSnakeCase($input->getArgument('name'));
        $timestamp = date('Y_m_d_His');
        $filename  = "{$timestamp}_{$name}.php";
        $dir       = $this->basePath . '/database/migrations';
        $path      = $dir . '/' . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        file_put_contents($path, $this->stub($name));

        $output->writeln("<info>  CREATE</info> database/migrations/{$filename}");

        return Command::SUCCESS;
    }

    private function stub(string $name): string
    {
        $table = $this->extractTableName($name);

        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Database\Capsule\Manager as Capsule;
        use Illuminate\Database\Schema\Blueprint;

        if (!Capsule::schema()->hasTable('{$table}')) {
            Capsule::schema()->create('{$table}', function (Blueprint \$table) {
                \$table->id();
                \$table->timestamps();
            });
        }
        PHP;
    }

    /** create_posts_table → posts, add_bio_to_users → users */
    private function extractTableName(string $name): string
    {
        if (preg_match('/(?:create|drop|alter|add\w*to|add\w*from)_(.+?)(?:_table)?$/', $name, $m)) {
            return $m[1];
        }
        return $name;
    }

    private function toSnakeCase(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $name = preg_replace('/[A-Z]/', '_$0', lcfirst($name));
        return strtolower(preg_replace('/_+/', '_', $name));
    }
}
