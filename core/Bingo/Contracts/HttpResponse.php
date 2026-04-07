<?php

declare(strict_types=1);

namespace Bingo\Contracts;

/**
 * Framework HTTP response contract built on Symfony HttpFoundation.
 *
 * Implemented by {@see \Bingo\Http\Response} (JSON, strings, …) and
 * {@see \Bingo\Http\StreamedResponse} (SSE, raw streams).
 */
interface HttpResponse
{
    /**
     * Send headers and content to the client.
     */
    public function send(): static;
}
