<?php

declare(strict_types=1);

namespace Bingo\Discovery\Discoverers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;

/**
 * Discovers console commands by scanning app/Console/Commands for classes
 * that extend Symfony\Component\Console\Command\Command.
 */
class CommandDiscoverer implements DiscovererInterface
{
    public function __construct(
        private readonly string $appPath,
    ) {}

    public function type(): string
    {
        return 'commands';
    }

    public function discover(): array
    {
        $commands    = [];
        $commandPath = $this->appPath . '/Console/Commands';

        if (!is_dir($commandPath)) {
            return [];
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($commandPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->getClassFromFile($file->getPathname());
            if (!$class || !class_exists($class)) {
                continue;
            }

            // Check if it extends Symfony Command
            if (is_subclass_of($class, Command::class)) {
                $commands[] = $class;
            }
        }

        return $commands;
    }

    /**
     * Extract fully-qualified class name from a PHP file.
     */
    private function getClassFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        // Extract namespace
        if (!preg_match('/namespace\s+([^;]+);/m', $contents, $matches)) {
            return null;
        }
        $namespace = $matches[1];

        // Extract class name
        if (!preg_match('/class\s+(\w+)/m', $contents, $matches)) {
            return null;
        }
        $className = $matches[1];

        return $namespace . '\\' . $className;
    }
}
