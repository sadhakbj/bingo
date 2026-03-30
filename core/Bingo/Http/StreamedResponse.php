<?php

declare(strict_types=1);

namespace Bingo\Http;

use Bingo\Contracts\HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

/**
 * Bingo-branded streaming response; same behavior as Symfony's StreamedResponse.
 */
class StreamedResponse extends SymfonyStreamedResponse implements HttpResponse
{
}
