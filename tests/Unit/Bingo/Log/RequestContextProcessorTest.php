<?php

declare(strict_types = 1);

namespace Tests\Unit\Bingo\Log;

use Bingo\Log\RequestContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class RequestContextProcessorTest extends TestCase
{
    private function makeRecord(): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel : 'test',
            level   : Level::Info,
            message : 'test message',
        );
    }

    public function test_no_extra_added_when_request_id_not_set(): void
    {
        $processor = new RequestContextProcessor();
        $record    = $processor($this->makeRecord());

        $this->assertArrayNotHasKey('request_id', $record->extra);
        $this->assertArrayNotHasKey('trace_id', $record->extra);
        $this->assertArrayNotHasKey('span_id', $record->extra);
    }

    public function test_sets_request_id_in_extra(): void
    {
        $processor = new RequestContextProcessor();
        $processor->setRequestId('abc-123');
        $record = $processor($this->makeRecord());

        $this->assertSame('abc-123', $record->extra['request_id']);
    }

    public function test_request_id_updates_after_setRequestId(): void
    {
        $processor = new RequestContextProcessor();
        $processor->setRequestId('first');
        $processor->setRequestId('second');
        $record = $processor($this->makeRecord());

        $this->assertSame('second', $record->extra['request_id']);
    }

    public function test_existing_extra_fields_are_preserved(): void
    {
        $processor = new RequestContextProcessor();
        $processor->setRequestId('req-1');
        $base = $this->makeRecord()->with(extra: ['custom_key' => 'custom_value']);

        $record = $processor($base);

        $this->assertSame('custom_value', $record->extra['custom_key']);
        $this->assertSame('req-1', $record->extra['request_id']);
    }
}
