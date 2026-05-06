<?php

declare(strict_types = 1);

namespace Tests\Stubs\Controllers;

use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Header;
use Bingo\Http\Response;

#[ApiController('/stub-meta')]
#[Header('X-From-Class', 'class')]
class StubMetaAttributesController
{
    #[Get('/combined')]
    #[Header('X-From-Method', 'method')]
    #[Header('X-From-Class', 'method-overrides')]
    public function combined(): Response
    {
        return Response::json(['ok' => true]);
    }
}
