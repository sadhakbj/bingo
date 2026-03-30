<?php

declare(strict_types=1);

namespace Bingo\Console;

use Bingo\Application;
use Bingo\Console\Command\GenerateCommandCommand;
use Bingo\Console\Command\GenerateControllerCommand;
use Bingo\Console\Command\GenerateExceptionCommand;
use Bingo\Console\Command\GenerateMiddlewareCommand;
use Bingo\Console\Command\GenerateMigrationCommand;
use Bingo\Console\Command\GenerateModelCommand;
use Bingo\Console\Command\GenerateServiceCommand;
use Bingo\Console\Command\MigrateCommand;
use Bingo\Console\Command\ServeCommand;
use Bingo\Console\Command\ShowRoutesCommand;
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
