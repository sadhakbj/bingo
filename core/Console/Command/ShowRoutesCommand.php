<?php

declare(strict_types=1);

namespace Core\Console\Command;

use Core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowRoutesCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('show:routes')
            ->setDescription('List all registered routes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $router = $this->app->getRouter();
        $routes = $router->getRoutes();

        if (empty($routes)) {
            $output->writeln('<comment>No routes registered.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Method', 'Path', 'Controller', 'Action', 'Middleware']);

        foreach ($routes as $name => $route) {
            $defaults   = $route->getDefaults();
            $middleware = $router->getMiddlewaresForRoute($name);

            $table->addRow([
                implode('|', $route->getMethods()),
                $route->getPath(),
                $defaults['_controller'] ?? '',
                $defaults['_action']     ?? '',
                implode(', ', $middleware),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
