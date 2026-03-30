<?php

declare(strict_types=1);

namespace Core\Console\Command;

use Core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{
    public function __construct(
        private readonly string      $basePath,
        private readonly Application $app,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('db:migrate')
            ->setAliases(['db:m'])
            ->setDescription('Run all pending database migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->basePath . '/database/migrations';

        if (!is_dir($dir)) {
            $output->writeln('<error>  No migrations directory found at database/migrations</error>');
            return Command::FAILURE;
        }

        $files = glob($dir . '/*.php');

        if (empty($files)) {
            $output->writeln('<comment>  Nothing to migrate.</comment>');
            return Command::SUCCESS;
        }

        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            require_once $file;
            $output->writeln("<info>  Migrated</info>  {$name}");
        }

        $output->writeln('');
        $output->writeln('<info>  Done.</info>');

        return Command::SUCCESS;
    }
}
