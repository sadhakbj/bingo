<?php

declare(strict_types=1);

namespace Tests\Stubs\Controllers;

use Bingo\Attributes\ApiController;
use Bingo\Attributes\Get;
use Bingo\Attributes\Post;
use Bingo\Attributes\Route\Param;
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
}
