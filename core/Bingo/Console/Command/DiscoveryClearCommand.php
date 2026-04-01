<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DiscoveryClearCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('discovery:clear')
            ->setDescription('Clear the discovery cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cachePath = base_path('storage/framework/discovery.php');

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $output->writeln('<info>✓ Discovery cache cleared!</info>');
            $output->writeln('');
            $output->writeln('The cache will be rebuilt automatically on the next request.');
        } else {
            $output->writeln('<comment>No cache file found.</comment>');
        }

        return Command::SUCCESS;
    }
}
