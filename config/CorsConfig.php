<?php

declare(strict_types=1);

namespace Config;

use Bingo\Attributes\Config\Env;

final readonly class CorsConfig
{
    public function __construct(
        #[Env('CORS_ALLOWED_ORIGINS', default: '*')]
        public string|array $allowedOrigins,

        #[Env('CORS_ALLOWED_METHODS', default: 'GET,POST,PUT,PATCH,DELETE,OPTIONS')]
        public string $allowedMethods,

        #[Env('CORS_ALLOWED_HEADERS', default: '*')]
        public string $allowedHeaders,

        #[Env('CORS_EXPOSED_HEADERS', default: '')]
        public string $exposedHeaders,

        #[Env('CORS_MAX_AGE', default: 86400)]
        public int $maxAge,

        #[Env('CORS_SUPPORTS_CREDENTIALS', default: false)]
        public bool $supportsCredentials,
    ) {}

    /**
     * Get allowed origins as array.
     * Handles both string ('*' or 'http://localhost:3000,https://app.com')
     * and array input.
     */
    public function getAllowedOrigins(): array
    {
        if (is_array($this->allowedOrigins)) {
            return $this->allowedOrigins;
        }

        // If single asterisk, return as-is for wildcard
        if ($this->allowedOrigins === '*') {
            return ['*'];
        }

        // Split comma-separated string
        return array_map('trim', explode(',', $this->allowedOrigins));
    }
}
