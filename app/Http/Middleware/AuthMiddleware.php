<?php

declare(strict_types=1);

namespace App\Http\Middleware;

class AuthMiddleware
{
    public function handle()
    {
        // Auth logic here
        return true;
    }
}
