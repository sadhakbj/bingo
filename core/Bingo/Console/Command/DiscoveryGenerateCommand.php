<?php

declare(strict_types = 1);

namespace Bingo\Console\Command;

use Bingo\Discovery\DiscoveryManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DiscoveryGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('discovery:generate')
            ->setAliases(['discovery:cache'])
            ->setDescription('Generate discovery cache for production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>Generating discovery cache...</comment>');
        $output->writeln('');

        $manager = new DiscoveryManager(
            cacheDir     : base_path('storage/framework/discovery'),
            appPath      : base_path('app'),
            coreBingoPath: dirname(__DIR__, 2), // core/Bingo/
            isProduction : false, // Force discovery even if APP_ENV=production
        );

        $discovered = $manager->rebuild();

        $output->writeln('<info>✓ Discovery cache generated successfully!</info>');
        $output->writeln('');
        $output->writeln('<comment>Discovered:</comment>');
        $output->writeln('  Controllers: <info>' . count($discovered['controllers']) . '</info>');

        $routeCount = 0;
        foreach ($discovered['controllers'] as $controller) {
            $routeCount += count($controller['routes']);
        }
        $output->writeln('  Routes:      <info>' . $routeCount . '</info>');
        $output->writeln('  Commands:    <info>' . count($discovered['commands']) . '</info>');
        $output->writeln('  Bindings:    <info>' . count($discovered['bindings']) . '</info>');
        $output->writeln('  Providers:   <info>' . count($discovered['providers']) . '</info>');
        $output->writeln('');
        $output->writeln('<comment>Cache location:</comment> storage/framework/discovery/');

        return Command::SUCCESS;
    }
}
