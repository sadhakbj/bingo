<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Bingo\Exceptions\Http\HttpException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateExceptionCommand extends Command
{
    public function __construct(private readonly string $basePath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate:exception')
            ->setAliases(['g:exception'])
            ->setDescription('Generate a new HTTP exception class in app/Exceptions')
            ->addArgument('name', InputArgument::REQUIRED, 'Exception name (e.g. PaymentRequired, SubscriptionExpiredException)')
            ->addOption(
                'status',
                's',
                InputOption::VALUE_REQUIRED,
                'HTTP status code (default: 400)',
                '400',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name   = (string) $input->getArgument('name');
        $status = (int) $input->getOption('status');

        if ($status < 100 || $status > 599) {
            $output->writeln('<error>  Status code must be between 100 and 599.</error>');

            return Command::FAILURE;
        }

        $className = $this->normalizeClassName($name);
        $path      = $this->basePath . '/app/Exceptions/' . $className . '.php';

        if (file_exists($path)) {
            $output->writeln("<error>  Already exists:</error> app/Exceptions/{$className}.php");

            return Command::FAILURE;
        }

        $defaultMessage = $this->defaultMessage($className, $status);

        file_put_contents($path, $this->stub($className, $status, $defaultMessage));

        $output->writeln("<info>  CREATE</info> app/Exceptions/{$className}.php");
        $output->writeln('');
        $output->writeln("  Throw from services or controllers:");
        $output->writeln("    throw new \\App\\Exceptions\\{$className}('Optional message');");

        return Command::SUCCESS;
    }

    /**
     * Prefer framework phrase for the status; otherwise derive a title from the class name.
     */
    private function defaultMessage(string $className, int $status): string
    {
        $phrase = HttpException::phraseForStatusCode($status);
        if ($phrase !== 'HTTP Error') {
            return $phrase;
        }

        $base = preg_replace('/Exception$/', '', $className) ?? $className;

        return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $base)) ?: 'HTTP Error';
    }

    private function normalizeClassName(string $name): string
    {
        $name = str_replace(['/', '\\'], '', trim($name));
        $name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));

        return str_ends_with($name, 'Exception') ? $name : $name . 'Exception';
    }

    private function stub(string $className, int $status, string $defaultMessage): string
    {
        $escapedMessage = addslashes($defaultMessage);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Exceptions;

        final class {$className} extends HttpException
        {
            public function __construct(string \$message = '{$escapedMessage}', ?\\Throwable \$previous = null, ?string \$description = null)
            {
                parent::__construct({$status}, \$message, \$previous, \$description);
            }
        }
        PHP;
    }
}
