<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Http\Sse;

use Bingo\Http\Response;
use Bingo\Http\Sse\SseEncoder;
use Bingo\Http\Sse\StreamedEvent;
use Bingo\Http\StreamedResponse;
use PHPUnit\Framework\TestCase;

class SseEncoderTest extends TestCase
{
    public function test_streamed_event_emits_event_id_and_data(): void
    {
        $frame = SseEncoder::chunkToFrame(new StreamedEvent('update', ['n' => 1], '42'));

        $this->assertStringContainsString("id: 42\n", $frame);
        $this->assertStringContainsString("event: update\n", $frame);
        $this->assertStringContainsString('data: {"n":1}', $frame);
        $this->assertStringEndsWith("\n\n", $frame);
    }

    public function test_multiline_data_splits_per_spec(): void
    {
        $frame = SseEncoder::encodeFrame('msg', "line1\nline2", null);

        $this->assertStringContainsString("data: line1\n", $frame);
        $this->assertStringContainsString("data: line2\n", $frame);
    }

    public function test_plain_yield_uses_default_message_event(): void
    {
        $frame = SseEncoder::chunkToFrame(['x' => true]);

        $this->assertStringContainsString('event: message', $frame);
        $this->assertStringContainsString('data: {"x":true}', $frame);
    }

    public function test_event_stream_response_headers_and_default_end_sentinel(): void
    {
        $response = Response::eventStream(static function (): \Generator {
            yield new StreamedEvent('ping', 'ok');
        });

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));

        $out = $this->captureStreamedBody($response);

        $this->assertStringContainsString("event: ping\n", $out);
        $this->assertStringContainsString("data: ok\n", $out);
        $this->assertStringContainsString('data: ' . SseEncoder::DEFAULT_END_DATA, $out);
    }

    public function test_custom_end_stream_with_replaces_default(): void
    {
        $response = Response::eventStream(
            static fn(): array => [],
            new StreamedEvent('done', '[END]'),
        );

        $out = $this->captureStreamedBody($response);

        $this->assertStringContainsString("event: done\n", $out);
        $this->assertStringContainsString("data: [END]\n", $out);
        $this->assertStringNotContainsString(SseEncoder::DEFAULT_END_DATA, $out);
    }

    public function test_raw_stream_is_not_sse_framed(): void
    {
        $response = Response::stream(static function (): void {
            echo 'chunk';
        });

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('chunk', $this->captureStreamedBody($response));
    }

    private function captureStreamedBody(StreamedResponse $response): string
    {
        $captured = '';
        ob_start(static function (string $buffer, int $phase) use (&$captured): string {
            $captured .= $buffer;

            return '';
        }, 4096);
        $response->sendContent();
        ob_end_flush();

        return $captured;
    }
}
