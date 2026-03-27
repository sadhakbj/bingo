<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\LogMiddleware;
use App\Models\User;
use Core\Attributes\Middleware;
use Core\Attributes\Route;
use Core\Http\Request;
use Core\Http\Response;

class HomeController
{
    #[Route('/', 'GET')]
    #[Middleware([LogMiddleware::class])]
    public function index(Request $request)
    {
        $users = User::query()->get();
        return Response::json($users);
    }

    #[Route('/about', 'GET')]
    #[Middleware([LogMiddleware::class, AuthMiddleware::class])]
    public function about()
    {
        return "About us page";
    }

    #[Route('/contact', 'POST')]
    #[Middleware([LogMiddleware::class])]
    public function contact()
    {
        return "Contact form submitted";
    }
}
