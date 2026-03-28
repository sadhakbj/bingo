<?php

declare(strict_types=1);

if (!function_exists('base_path')) {
    /**
     * Return the absolute path to the application root (where composer.json lives).
     * Optionally append a sub-path.
     */
    function base_path(string $path = ''): string
    {
        static $base = null;

        if ($base === null) {
            $dir = __DIR__;
            while (!file_exists($dir . '/composer.json')) {
                $parent = dirname($dir);
                if ($parent === $dir) {
                    $base = dirname(__DIR__);
                    break;
                }
                $dir = $parent;
            }
            $base ??= $dir;
        }

        return $base . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('database_path')) {
    /**
     * Return the absolute path to the database directory.
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('env')) {
    /**
     * Get an environment variable, returning $default if not set or empty.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            default            => $value,
        };
    }
}
