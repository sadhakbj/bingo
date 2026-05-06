<?php

declare(strict_types = 1);

namespace Bingo\Log;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that injects the current request_id into every log record.
 *
 * Usage: push onto a Logger instance, then call setRequestId() at the start
 * of each request (e.g. from a middleware or front controller).
 */
final class RequestContextProcessor implements ProcessorInterface
{
    private string $requestId = '';

    public function setRequestId(string $id): void
    {
        $this->requestId = $id;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->requestId === '') {
            return $record;
        }

        return $record->with(extra: array_merge($record->extra, [
            'request_id' => $this->requestId,
        ]));
    }
}
