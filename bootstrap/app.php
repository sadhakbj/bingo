<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The Application boots automatically:
|   - Loads .env (optional — Docker/K8s inject vars directly)
|   - Builds typed AppConfig and DatabaseConfig from environment
|   - Boots Eloquent with all configured connections
|   - Wires DI container, router, and middleware pipeline
|
*/

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UsersController;
use Core\Application;

$app = Application::create();

/*
|--------------------------------------------------------------------------
| Register Services (Dependency Injection)
|--------------------------------------------------------------------------
|
| Concrete classes with typed constructor deps are auto-resolved — no
| registration needed. Only register:
|   - Interface → concrete bindings
|   - Pre-built objects you want to share
|
| AppConfig and DatabaseConfig are pre-registered automatically.
|
*/

// $app->singleton(\App\Contracts\CacheInterface::class, \App\Services\RedisCache::class);

/*
|--------------------------------------------------------------------------
| Register Controllers
|--------------------------------------------------------------------------
*/

$app->controllers([
    HomeController::class,
    UsersController::class,
]);

return $app;
