<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateMiddlewareCommand extends Command
{
    public function __construct(private readonly string $basePath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate:middleware')
            ->setAliases(['g:middleware'])
            ->setDescription('Generate a new middleware class')
            ->addArgument('name', InputArgument::REQUIRED, 'Middleware name (e.g. Auth, AuthMiddleware)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name      = $input->getArgument('name');
        $className = $this->normalize($name, 'Middleware');
        $path      = $this->basePath . '/app/Http/Middleware/' . $className . '.php';

        if (file_exists($path)) {
            $output->writeln("<error>  Already exists:</error> app/Http/Middleware/{$className}.php");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($className));

        $output->writeln("<info>  CREATE</info> app/Http/Middleware/{$className}.php");
        $output->writeln('');
        $output->writeln("  Use globally in <comment>bootstrap/app.php</comment>:");
        $output->writeln("    \$app->use(\\App\\Http\\Middleware\\{$className}::class);");
        $output->writeln('');
        $output->writeln("  Or per-route:");
        $output->writeln("    #[Middleware([\\App\\Http\\Middleware\\{$className}::class])]");

        return Command::SUCCESS;
    }

    private function stub(string $className): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Http\Middleware;

        use Bingo\Http\Request;use Bingo\Http\Response;

        class {$className} implements MiddlewareInterface
        {
            public function handle(Request \$request, callable \$next): Response
            {
                return \$next(\$request);
            }
        }
        PHP;
    }

    private function normalize(string $name, string $suffix): string
    {
        $name = ucfirst($name);
        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }
}
