<?php

declare(strict_types=1);

namespace Bingo\Http\Sse;

/**
 * Formats frames per https://html.spec.whatwg.org/multipage/server-sent-events.html
 */
final class SseEncoder
{
    public const DEFAULT_EVENT = 'message';

    public const DEFAULT_END_DATA = '</stream>';

    /**
     * Encode a single logical event to wire format.
     */
    public static function encodeFrame(?string $event, string $dataPayload, ?string $id = null): string
    {
        $lines = [];

        if ($id !== null && $id !== '') {
            $lines[] = 'id: ' . self::escapeLine($id);
        }

        if ($event !== null && $event !== '') {
            $lines[] = 'event: ' . self::escapeLine($event);
        }

        foreach (preg_split("/\r\n|\n|\r/", $dataPayload) ?: [] as $line) {
            $lines[] = 'data: ' . $line;
        }

        $lines[] = '';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public static function normalizeData(string|array|object $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param StreamedEvent|array|object|string $chunk Yielded value from producer
     */
    public static function chunkToFrame(mixed $chunk, string $defaultEvent = self::DEFAULT_EVENT): string
    {
        if ($chunk instanceof StreamedEvent) {
            $event = $chunk->event ?? $defaultEvent;
            $data  = self::normalizeData($chunk->data);

            return self::encodeFrame($event, $data, $chunk->id);
        }

        $data = self::normalizeData($chunk);

        return self::encodeFrame($defaultEvent, $data, null);
    }

    private static function escapeLine(string $s): string
    {
        return str_replace(["\r", "\n"], '', $s);
    }
}
