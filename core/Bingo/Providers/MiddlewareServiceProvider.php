<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Http\Middleware\BodyParserMiddleware;
use Bingo\Http\Middleware\CompressionMiddleware;
use Bingo\Http\Middleware\CorsMiddleware;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Middleware\RateLimitMiddleware;
use Bingo\Http\Middleware\RequestIdMiddleware;
use Bingo\Http\Middleware\SecurityHeadersMiddleware;
use Bingo\RateLimit\Contracts\RateLimiterStore;
use Bingo\RateLimit\RateLimiter;
use Config\AppConfig;
use Config\CorsConfig;
use Config\RateLimitConfig;

#[ServiceProvider]
class MiddlewareServiceProvider
{
    public function boot(
        MiddlewarePipeline $pipeline,
        RateLimiterStore $store,
        AppConfig $appConfig,
        RateLimitConfig $rateLimitConfig,
        CorsConfig $corsConfig,
    ): void {
        $isProduction = $appConfig->env === 'production';

        $pipeline->addGlobal(CorsMiddleware::fromConfig($corsConfig));
        $pipeline->addGlobal($isProduction ? BodyParserMiddleware::production() : BodyParserMiddleware::json());
        $pipeline->addGlobal(CompressionMiddleware::create());
        $pipeline->addGlobal(
            $isProduction ? SecurityHeadersMiddleware::production() : SecurityHeadersMiddleware::create(),
        );
        $pipeline->addGlobal(RequestIdMiddleware::create());

        if ($rateLimitConfig->enabled) {
            $pipeline->addGlobal(RateLimitMiddleware::create(
                limiter      : new RateLimiter($store),
                limit        : $rateLimitConfig->maxRequests,
                windowSeconds: $rateLimitConfig->window,
            ));
        }
    }
}
