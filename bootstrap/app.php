<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new application instance
| which serves as the "glue" for all the components and is
| the IoC container for the system binding all of the various parts.
|
*/

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UsersController;
use Core\Application;
use Core\Database\Database;

$app = Application::create([
    'database' => true,
]);

/*
|--------------------------------------------------------------------------
| Bootstrap Application
|--------------------------------------------------------------------------
|
| Here we will load the application. This basically does the work
| of setting up middleware pipeline and database connections.
|
*/

// Initialize database if enabled
if ($app->getConfig()['database']) {
    Database::setup();
}

/*
|--------------------------------------------------------------------------
| Register Services (Dependency Injection)
|--------------------------------------------------------------------------
|
| Only needed for:
|   - Interface → concrete bindings  ($app->singleton(CacheInterface::class, RedisCache::class))
|   - Pre-built config/scalar objects ($app->instance(Config::class, new Config([...])))
|
| Concrete classes with typed constructor deps are auto-resolved — no registration needed.
| PostService, UserService, OrderService etc. just work via type-hinting.
|
*/

// $app->singleton(\App\Services\UserService::class);
// $app->singleton(\App\Contracts\CacheInterface::class, \App\Services\RedisCache::class);
// $app->instance(\App\Config::class, new \App\Config(['key' => 'value']));

/*
|--------------------------------------------------------------------------
| Register Controllers
|--------------------------------------------------------------------------
|
| Register all application controllers for route discovery
|
*/

$app->controllers([
    HomeController::class,
    UsersController::class,
]);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
