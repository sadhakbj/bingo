<?php

declare(strict_types=1);

namespace Bingo\Http\Sse;

/**
 * One SSE frame (event + payload), similar in spirit to Laravel's StreamedEvent.
 *
 * @see https://laravel.com/docs/responses#event-streams
 */
final readonly class StreamedEvent
{
    /**
     * @param string|null    $event SSE event name; null uses default "message"
     * @param string|array   $data  Raw string or JSON-encodable value
     * @param string|null    $id    Optional SSE id field
     */
    public function __construct(
        public ?string $event,
        public string|array|object $data,
        public ?string $id = null,
    ) {}

    /**
     * Named constructor matching Laravel-style calls: StreamedEvent::make(event: 'update', data: $x)
     */
    public static function make(?string $event = null, string|array|object $data = '', ?string $id = null): self
    {
        return new self($event, $data, $id);
    }
}
