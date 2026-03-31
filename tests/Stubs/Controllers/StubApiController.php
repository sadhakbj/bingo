<?php

declare(strict_types=1);

namespace Tests\Stubs\Controllers;

use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Header;
use Bingo\Attributes\Route\HttpCode;
use Bingo\Attributes\Route\Param;
use Bingo\Attributes\Route\Post;
use Bingo\Attributes\Route\Query;
use Bingo\Http\Response;

#[ApiController('/stub')]
class StubApiController
{
    #[Get('/')]
    public function index(): Response
    {
        return Response::json(['index' => true]);
    }

    #[Get('/hello')]
    public function hello(): Response
    {
        return Response::json(['message' => 'hello']);
    }

    #[Get('/users/{id}')]
    public function show(#[Param('id')] int $id): Response
    {
        return Response::json(['id' => $id]);
    }

    #[Post('/create')]
    public function create(): Response
    {
        return Response::json(['created' => true], 201);
    }

    #[Get('/search')]
    public function search(#[Query('q')] ?string $q, #[Query('page')] int $page = 1): Response
    {
        return Response::json(['q' => $q, 'page' => $page]);
    }

    #[Get('/meta-status')]
    #[HttpCode(202)]
    public function metaStatus(): Response
    {
        return Response::json(['accepted' => true]);
    }

    #[Get('/meta-http-code')]
    #[HttpCode(418)]
    public function metaHttpCode(): Response
    {
        return Response::json(['teapot' => true]);
    }

    #[Get('/meta-explicit-wins')]
    #[HttpCode(204)]
    public function metaExplicitWins(): Response
    {
        return Response::json(['created' => true], 201);
    }

    #[Get('/meta-headers')]
    #[Header('X-Stub-A', 'a')]
    #[Header('X-Stub-B', 'b')]
    public function metaHeaders(): Response
    {
        return Response::json(['ok' => true], 200, ['X-Stub-Method' => 'method-only']);
    }

    #[Get('/meta-header-controller-wins')]
    #[Header('X-Stub-Both', 'from-attribute')]
    public function metaHeaderControllerWins(): Response
    {
        return Response::json(['ok' => true], 200, ['X-Stub-Both' => 'from-controller']);
    }
}
