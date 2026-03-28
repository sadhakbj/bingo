<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\LogMiddleware;
use App\Models\User;
use Core\Attributes\Middleware;
use Core\Attributes\Route\Route;
use Core\Http\Request;
use Core\Http\Response;

class HomeController
{
    #[Route('/', 'GET')]
    #[Middleware([LogMiddleware::class])]
    public function index(Request $request): Response
    {
        $users = User::query()->get();
        return Response::json($users);
    }

    #[Route('/about', 'GET')]
    #[Middleware([LogMiddleware::class, AuthMiddleware::class])]
    public function about(): string
    {
        return "About us page";
    }

    #[Route('/contact', 'POST')]
    #[Middleware([LogMiddleware::class])]
    public function contact(): string
    {
        return "Contact form submitted";
    }
}
