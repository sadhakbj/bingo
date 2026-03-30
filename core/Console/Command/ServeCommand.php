<?php

declare(strict_types=1);

namespace Core\Console\Command;

use Core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    public function __construct(
        private readonly string      $basePath,
        private readonly Application $app,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('serve')
            ->setDescription('Start the Bingo development server')
            ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'Host to bind',    '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to listen on', '8000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host   = $input->getOption('host');
        $port   = $input->getOption('port');
        $public = $this->basePath . '/public';

        // ── Boot log ─────────────────────────────────────────────────────────
        $this->log($output, 'LOG', 'Kernel', 'Starting Bingo application...');

        foreach ($this->app->getRouter()->getRoutes() as $name => $route) {
            $methods = implode('|', $route->getMethods());
            $path    = $route->getPath();
            $this->log(
                $output, 'LOG', 'RouterExplorer',
                "Mapped {<fg=cyan>{$methods}</> <fg=white>{$path}</>} route",
            );
        }

        $this->log($output, 'LOG', 'Server', 'Bingo application successfully started');

        // ── Spawn PHP built-in server ─────────────────────────────────────────
        // stderr is captured so we can reformat each line.
        // stdout is piped to /dev/null (the built-in server doesn't use it).
        $process = proc_open(
            PHP_BINARY . ' -S ' . escapeshellarg("{$host}:{$port}") . ' -t ' . escapeshellarg($public),
            [0 => STDIN, 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            $this->log($output, 'ERROR', 'Server', 'Failed to start the development server.');
            return Command::FAILURE;
        }

        $serverReady = false;

        while (!feof($pipes[2])) {
            $line = fgets($pipes[2]);
            if ($line === false) break;
            $line = trim($line);
            if ($line === '') continue;

            // Skip low-level connection chatter
            if (preg_match('/\bAccepted\b|\bClosing\b/', $line)) continue;

            // "Development Server (http://...) started" → show our ready message
            if (!$serverReady && str_contains($line, 'Development Server')) {
                $serverReady = true;
                $this->log($output, 'LOG', 'Server',
                    "Application is running on: <comment>http://{$host}:{$port}</comment>");
                $this->log($output, 'LOG', 'Server',
                    'Press <comment>Ctrl+C</comment> to stop');
                continue;
            }

            // Request line: [timestamp] ip:port [status]: METHOD /path
            if (preg_match('/\[.+?\] [\d.]+:\d+ \[(\d+)\]: (\w+) (.+)/', $line, $m)) {
                [, $status, $method, $path] = $m;

                $level = match(true) {
                    $status >= 500 => 'ERROR',
                    $status >= 400 => 'WARN',
                    default        => 'LOG',
                };

                $statusColor = match(true) {
                    $status >= 500 => 'fg=red',
                    $status >= 400 => 'fg=yellow',
                    $status >= 300 => 'fg=cyan',
                    default        => 'fg=green',
                };

                $methodColor = match($method) {
                    'GET'              => 'fg=cyan',
                    'POST'             => 'fg=green',
                    'PUT', 'PATCH'     => 'fg=yellow',
                    'DELETE'           => 'fg=red',
                    default            => 'fg=white',
                };

                $this->log($output, $level, 'HTTP', sprintf(
                    '<%s>%-7s</>  %s  → <%s>%s</>',
                    $methodColor, $method, $path, $statusColor, $status,
                ));

                continue;
            }

            // Anything else: pass through dimmed
            $output->writeln('  <fg=gray>' . $line . '</>');
        }

        fclose($pipes[2]);
        proc_close($process);

        return Command::SUCCESS;
    }

    // ── Structured console log line ───────────────────────────────────────────

    private function log(OutputInterface $output, string $level, string $context, string $message): void
    {
        $levelTag = match($level) {
            'LOG'   => '<fg=green>',
            'WARN'  => '<fg=yellow>',
            'ERROR' => '<fg=red>',
            default => '<fg=white>',
        };

        $output->writeln(sprintf(
            ' <fg=yellow>[Bingo]</> <fg=gray>%s</>  - <fg=gray>%s</>   %s%s</>  <comment>[%s]</comment>  %s',
            str_pad((string) getmypid(), 5),
            date('m/d/Y, h:i:s A'),
            $levelTag,
            str_pad($level, 5),
            $context,
            $message,
        ));
    }
}
