<?php

declare(strict_types=1);

namespace Config;

use Core\Attributes\Config\Env;

final readonly class AppConfig
{
    public function __construct(
        #[Env('APP_NAME', default: 'Bingo')]
        public string $name,

        #[Env('APP_ENV', default: 'development')]
        public string $env,

        #[Env('APP_DEBUG', default: false)]
        public bool $debug,

        #[Env('APP_URL', default: 'http://localhost:8000')]
        public string $url,
    ) {}

}
