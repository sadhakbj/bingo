<?php

declare(strict_types=1);

namespace Bingo\Http;

use Bingo\Contracts\HttpResponse;
use Bingo\Http\Sse\SseEncoder;
use Bingo\Http\Sse\StreamedEvent;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response extends SymfonyResponse implements HttpResponse
{
    public static function json($data, int $status = 200, array $headers = [], int $options = 0): self
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
        ], $headers);
        $json = json_encode($data, $options | JSON_UNESCAPED_UNICODE);
        return new self($json, $status, $headers);
    }

    /**
     * @param callable(): iterable<int|string|array|object|StreamedEvent> $producer
     */
    public static function eventStream(
        callable $producer,
        ?StreamedEvent $endStreamWith = null,
        int $status = 200,
        array $headers = [],
    ): StreamedResponse {
        $headers = array_merge([
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ], $headers);

        $callback = static function () use ($producer, $endStreamWith): void {
            foreach ($producer() as $chunk) {
                echo SseEncoder::chunkToFrame($chunk);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            $end = $endStreamWith ?? new StreamedEvent(null, SseEncoder::DEFAULT_END_DATA);
            echo SseEncoder::chunkToFrame($end);
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        };

        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Raw streamed body (chunked body without SSE framing).
     */
    public static function stream(callable $callback, int $status = 200, array $headers = []): StreamedResponse
    {
        $headers = array_merge([
            'X-Accel-Buffering' => 'no',
        ], $headers);

        return new StreamedResponse($callback, $status, $headers);
    }
}
