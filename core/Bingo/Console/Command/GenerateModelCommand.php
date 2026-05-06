<?php

declare(strict_types=1);

namespace Bingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateModelCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('generate:model')
            ->setAliases(['g:model'])
            ->setDescription('Generate a new Eloquent model')
            ->addArgument('name', InputArgument::REQUIRED, 'Model name (e.g. Post, User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $className = ucfirst($input->getArgument('name'));
        $path      = $this->basePath . '/app/Models/' . $className . '.php';

        if (file_exists($path)) {
            $output->writeln("<error>  Already exists:</error> app/Models/{$className}.php");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($className));

        $output->writeln("<info>  CREATE</info> app/Models/{$className}.php");

        return Command::SUCCESS;
    }

    private function stub(string $className): string
    {
        $table = strtolower($className) . 's';

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;

            class {$className} extends Model
            {
                protected \$table = '{$table}';

                protected \$fillable = [];

                protected \$hidden = [];
            }
            PHP;
    }
}
