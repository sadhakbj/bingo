<?php

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
| Register Controllers
|--------------------------------------------------------------------------
|
| Register all application controllers for route discovery
|
*/

$app->controllers([
    \App\Http\Controllers\HomeController::class,
    \App\Http\Controllers\UsersController::class,
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