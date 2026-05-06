<?php

declare(strict_types = 1);

namespace Tests\Stubs\Controllers;

use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\HttpCode;
use Bingo\Http\Response;

#[ApiController('/stub-class-status')]
#[HttpCode(203)]
class StubClassStatusController
{
    #[Get('/x')]
    public function x(): Response
    {
        return Response::json(['nonDefault' => false]);
    }

    #[Get('/y')]
    #[HttpCode(404)]
    public function y(): Response
    {
        return Response::json(['missing' => true]);
    }
}
