<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Models\User;
use App\Services\GreetingService;
use Bingo\Attributes\Middleware;
use Bingo\Attributes\Route\Param;
use Bingo\Attributes\Route\Route;
use Bingo\Http\Response;
use Bingo\Attributes\Route\Throttle;

class HomeController
{
    public function __construct(private readonly GreetingService $greetingService) {}

    #[Route('/greet/{name}', 'GET')]
    public function greet(#[Param('name')] string $name): Response
    {
        return Response::json(['message' => $this->greetingService->greet($name)]);
    }

    #[Route('/', 'GET')]
    #[Throttle(requests: 1, per: 60)]
    public function index(): Response
    {
        $users = User::query()->get();
        return Response::json($users);
    }

    #[Route('/about', 'GET')]
    #[Middleware([AuthMiddleware::class])]
    public function about(): string
    {
        return "About us page";
    }

    #[Route('/contact', 'POST')]
    public function contact(): string
    {
        return "Contact form submitted";
    }
}
