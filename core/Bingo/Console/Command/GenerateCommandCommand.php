<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommandCommand extends Command
{
    public function __construct(private readonly string $basePath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate:command')
            ->setAliases(['g:command'])
            ->setDescription('Generate a new console command')
            ->addArgument('name', InputArgument::REQUIRED, 'Command class name (e.g. SendEmails)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name      = $input->getArgument('name');
        $className = $this->normalize($name, 'Command');
        $cmdName   = $this->toCommandName($className);
        $dir       = $this->basePath . '/app/Console/Commands';
        $path      = $dir . '/' . $className . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        if (file_exists($path)) {
            $output->writeln("<error>  Already exists:</error> app/Console/Commands/{$className}.php");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($className, $cmdName));

        $output->writeln("<info>  CREATE</info> app/Console/Commands/{$className}.php");
        $output->writeln('');
        $output->writeln("  Register in <comment>bootstrap/console.php</comment>:");
        $output->writeln("    \$kernel->command(\\App\\Console\\Commands\\{$className}::class);");

        return Command::SUCCESS;
    }

    private function stub(string $className, string $cmdName): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Console\Commands;

        use Symfony\Component\Console\Command\Command;
        use Symfony\Component\Console\Input\InputInterface;
        use Symfony\Component\Console\Output\OutputInterface;

        class {$className} extends Command
        {
            // Inject services via constructor — the DI container resolves them automatically.
            // public function __construct(private readonly UserService \$users)
            // {
            //     parent::__construct();
            // }

            protected function configure(): void
            {
                \$this
                    ->setName('{$cmdName}')
                    ->setDescription('Describe what this command does');
            }

            protected function execute(InputInterface \$input, OutputInterface \$output): int
            {
                \$output->writeln('Running {$cmdName}...');

                return Command::SUCCESS;
            }
        }
        PHP;
    }

    private function normalize(string $name, string $suffix): string
    {
        $name = ucfirst($name);
        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }

    /** SendEmailsCommand → app:send-emails */
    private function toCommandName(string $className): string
    {
        $base = str_replace('Command', '', $className);
        $snake = strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($base)));
        return 'app:' . ltrim($snake, '-');
    }
}
