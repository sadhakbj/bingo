<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Attributes\Provider\Boots;
use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Config\ConfigLoader;
use Bingo\Http\Middleware\BodyParserMiddleware;
use Bingo\Http\Middleware\CompressionMiddleware;
use Bingo\Http\Middleware\CorsMiddleware;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Middleware\RequestIdMiddleware;
use Bingo\Http\Middleware\SecurityHeadersMiddleware;
use Config\CorsConfig;

#[ServiceProvider]
class MiddlewareServiceProvider
{
    #[Boots]
    public function boot(MiddlewarePipeline $pipeline): void
    {
        $cors = CorsMiddleware::fromConfig(ConfigLoader::load(CorsConfig::class));

        $pipeline->addGlobal($cors);
        $pipeline->addGlobal(BodyParserMiddleware::json());
        $pipeline->addGlobal(CompressionMiddleware::create());
        $pipeline->addGlobal(SecurityHeadersMiddleware::create());
        $pipeline->addGlobal(RequestIdMiddleware::create());
    }
}