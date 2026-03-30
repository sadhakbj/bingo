<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\LogMiddleware;
use App\Models\User;
use Bingo\Attributes\Middleware;
use Bingo\Attributes\Route\Route;
use Bingo\Http\Response;

class HomeController
{
    public function __construct()
    {
    }

    #[Route('/', 'GET')]
    #[Middleware([LogMiddleware::class])]
    public function index(): Response
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
