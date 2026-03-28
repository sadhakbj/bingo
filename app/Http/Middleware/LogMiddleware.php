<?php

declare(strict_types=1);

namespace App\Http\Middleware;

class LogMiddleware
{
    public function handle()
    {
        // Log logic here
        return true;
    }
}
