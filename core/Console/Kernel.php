<?php

declare(strict_types=1);

namespace Core\Console;

use Core\Application;
use Core\Console\Command\GenerateCommandCommand;
use Core\Console\Command\GenerateControllerCommand;
use Core\Console\Command\GenerateExceptionCommand;
use Core\Console\Command\GenerateMigrationCommand;
use Core\Console\Command\GenerateMiddlewareCommand;
use Core\Console\Command\GenerateModelCommand;
use Core\Console\Command\GenerateServiceCommand;
use Core\Console\Command\MigrateCommand;
use Core\Console\Command\ServeCommand;
use Core\Console\Command\ShowRoutesCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Kernel
{
    private ConsoleApplication $console;

    /** @var string[] */
    private array $userCommands = [];

    public function __construct(private readonly Application $app)
    {
        $this->console = new ConsoleApplication('Bingo', '1.0.0');
    }

    /**
     * Register an application-level command.
     * The class is resolved through the DI container, so constructor
     * dependencies (services, config, etc.) are injected automatically.
     */
    public function command(string $commandClass): static
    {
        $this->userCommands[] = $commandClass;
        return $this;
    }

    public function run(): void
    {
        $this->console->addCommands($this->resolveCommands());
        $this->console->run();
    }

    private function resolveCommands(): array
    {
        $basePath = $this->app->basePath();

        $builtin = [
            new ServeCommand($basePath, $this->app),
            new ShowRoutesCommand($this->app),
            new MigrateCommand($basePath, $this->app),
            new GenerateControllerCommand($basePath),
            new GenerateServiceCommand($basePath),
            new GenerateMiddlewareCommand($basePath),
            new GenerateExceptionCommand($basePath),
            new GenerateModelCommand($basePath),
            new GenerateMigrationCommand($basePath),
            new GenerateCommandCommand($basePath),
        ];

        // Resolve each user command through the DI container so their
        // typed constructor dependencies are injected automatically.
        $userCommands = array_map(
            fn(string $class) => $this->app->make($class),
            $this->userCommands,
        );

        return array_merge($builtin, $userCommands);
    }
}
