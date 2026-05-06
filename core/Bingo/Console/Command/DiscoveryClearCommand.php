<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Bingo\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DiscoveryClearCommand extends Command
{
    public function __construct(
        private readonly Application $app,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('discovery:clear')
            ->setDescription('Clear the discovery cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheDir = $this->app->frameworkPath('discovery');

        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*.php') as $file) {
                unlink($file);
            }
            $output->writeln('<info>✓ Discovery cache cleared!</info>');
            $output->writeln('');
            $output->writeln('The cache will be rebuilt automatically on the next request.');
        } else {
            $output->writeln('<comment>No cache directory found.</comment>');
        }

        return Command::SUCCESS;
    }
}
