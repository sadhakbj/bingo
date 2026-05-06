<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateServiceCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('generate:service')
            ->setAliases(['g:service'])
            ->setDescription('Generate a new service class')
            ->addArgument('name', InputArgument::REQUIRED, 'Service name (e.g. Users, UserService)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name      = $input->getArgument('name');
        $className = $this->normalize($name, 'Service');
        $path      = $this->basePath . '/app/Services/' . $className . '.php';

        if (file_exists($path)) {
            $output->writeln("<error>  Already exists:</error> app/Services/{$className}.php");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($className));

        $output->writeln("<info>  CREATE</info> app/Services/{$className}.php");

        return Command::SUCCESS;
    }

    private function stub(string $className): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\Services;

            class {$className}
            {
                //
            }
            PHP;
    }

    private function normalize(string $name, string $suffix): string
    {
        $name = ucfirst($name);
        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }
}
