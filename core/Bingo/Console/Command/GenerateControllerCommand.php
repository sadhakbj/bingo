<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateControllerCommand extends Command
{
    public function __construct(private readonly string $basePath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate:controller')
            ->setAliases(['g:controller'])
            ->setDescription('Generate a new controller')
            ->addArgument('name', InputArgument::REQUIRED, 'Controller name (e.g. Posts)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name      = $input->getArgument('name');
        $className = $this->normalize($name, 'Controller');
        $prefix    = '/' . strtolower(str_replace('Controller', '', $className));
        $path      = $this->basePath . '/app/Http/Controllers/' . $className . '.php';

        if (file_exists($path)) {
            $output->writeln("<error>  Already exists:</error> app/Http/Controllers/{$className}.php");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($className, $prefix));

        $output->writeln("<info>  CREATE</info> app/Http/Controllers/{$className}.php");
        $output->writeln('');
        $output->writeln("  Register in <comment>bootstrap/app.php</comment>:");
        $output->writeln("    \$app->controller(\\App\\Http\\Controllers\\{$className}::class);");

        return Command::SUCCESS;
    }

    private function stub(string $className, string $prefix): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Http\Controllers;

        use Bingo\Attributes\ApiController;use Bingo\Http\Response;

        #[ApiController('{$prefix}')]
        class {$className}
        {
            #[Get('/')]
            public function index(): Response
            {
                return Response::json([]);
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
