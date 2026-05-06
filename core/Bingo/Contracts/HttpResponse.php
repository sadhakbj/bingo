<?php

declare(strict_types = 1);

namespace Bingo\Contracts;

/**
 * Marker for framework HTTP responses built on Symfony HttpFoundation.
 *
 * Implemented by {@see \Bingo\Http\Response} (JSON, strings, …) and
 * {@see \Bingo\Http\StreamedResponse} (SSE, raw streams).
 */
interface HttpResponse
{
}
