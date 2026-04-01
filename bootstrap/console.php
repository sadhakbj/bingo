<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Console Bootstrap
|--------------------------------------------------------------------------
|
| This is the entry point for the Bingo CLI (bin/bingo).
| It is completely separate from the HTTP bootstrap (bootstrap/app.php).
|
| Register your application commands here using $kernel->command().
| Commands are resolved through the DI container, so constructor
| dependencies are injected automatically.
|
| Example:
|   $kernel->command(\App\Console\Commands\SendEmailsCommand::class);
|
*/

use Bingo\Console\Kernel;

// Boot the same application that HTTP uses — routes, DI bindings, and all.
// The console Kernel wraps it; it never calls $app->run() (that's the HTTP path).
$app = require __DIR__ . '/app.php';

$kernel = new Kernel($app);

// Framework commands are auto-registered by the kernel
// No need to manually register discovery:generate, discovery:clear, show:routes, etc.

// Register your application commands here:
// $kernel->command(\App\Console\Commands\SendEmailsCommand::class);

return $kernel;
