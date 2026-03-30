<?php

declare(strict_types=1);

namespace Tests\Stubs\Controllers;

use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Post;

class StubPlainController
{
    #[Get('/plain')]
    public function index(): string
    {
        return 'plain response';
    }

    #[Post('/plain')]
    public function store(): string
    {
        return 'stored';
    }
}
