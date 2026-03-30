<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Http\Middleware;

use Bingo\Http\Middleware\CompressionMiddleware;
use Bingo\Http\Request;
use Bingo\Http\Response;
use PHPUnit\Framework\TestCase;

class CompressionMiddlewareStreamTest extends TestCase
{
    public function test_skips_gzip_for_streamed_response(): void
    {
        $mw     = CompressionMiddleware::create();
        $stream = Response::eventStream(static fn (): array => []);

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);

        $response = $mw->handle($request, static fn () => $stream);

        $this->assertSame('', (string) $response->headers->get('Content-Encoding', ''));
    }

    public function test_skips_gzip_for_text_event_stream_content_type(): void
    {
        $mw = CompressionMiddleware::create();

        $plain = new Response(
            str_repeat('x', 2048),
            200,
            ['Content-Type' => 'text/event-stream; charset=UTF-8'],
        );

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);

        $response = $mw->handle($request, static fn () => $plain);

        $this->assertSame('', (string) $response->headers->get('Content-Encoding', ''));
    }
}
