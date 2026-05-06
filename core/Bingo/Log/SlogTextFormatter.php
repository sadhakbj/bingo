<?php

declare(strict_types=1);

namespace Bingo\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Formats log records in Go slog text style, with optional ANSI colors.
 *
 * Plain (LOG_FORMAT=text, piped / file):
 *   time=2024-01-15T12:03:44Z level=INFO msg="HTTP GET" method=GET path=/users status=200 duration_ms=4
 *
 * Colored (terminal):
 *   time=... level=INFO  msg="HTTP GET"  method=GET path=/users status=200 duration_ms=4
 *   (time dim, level colored by severity, msg bold, keys cyan, values default)
 */
final class SlogTextFormatter implements FormatterInterface
{
    // ANSI escape sequences
    private const RESET         = "\e[0m";
    private const BOLD          = "\e[1m";
    private const DIM           = "\e[2m";
    private const CYAN          = "\e[36m";
    private const GREEN         = "\e[32m";
    private const YELLOW        = "\e[33m";
    private const RED           = "\e[31m";
    private const BLUE          = "\e[34m";
    private const BRIGHT_RED    = "\e[91m";
    private const BRIGHT_YELLOW = "\e[93m";

    private const LEVEL_COLORS = [
        'DEBUG'     => self::BLUE,
        'INFO'      => self::GREEN,
        'NOTICE'    => self::CYAN,
        'WARNING'   => self::BRIGHT_YELLOW,
        'ERROR'     => self::RED,
        'CRITICAL'  => self::BRIGHT_RED,
        'ALERT'     => self::BRIGHT_RED,
        'EMERGENCY' => self::BRIGHT_RED,
    ];

    private const LEVEL_NAMES = [
        'DEBUG'     => 'DEBUG',
        'INFO'      => 'INFO',
        'NOTICE'    => 'NOTICE',
        'WARNING'   => 'WARN',
        'ERROR'     => 'ERROR',
        'CRITICAL'  => 'CRIT',
        'ALERT'     => 'ALERT',
        'EMERGENCY' => 'EMERG',
    ];

    public function __construct(
        private readonly bool $colors = false,
        private readonly string $timeFormat = \DateTimeInterface::RFC3339,
    ) {}

    public function format(LogRecord $record): string
    {
        $levelName = self::LEVEL_NAMES[$record->level->getName()] ?? $record->level->getName();
        $time      = $record->datetime->format($this->timeFormat);

        if ($this->colors) {
            $levelColor = self::LEVEL_COLORS[$record->level->getName()] ?? self::RESET;

            $parts = [
                self::DIM . 'time=' . $this->value($time) . self::RESET,
                $levelColor . 'level=' . sprintf('%-5s', $levelName) . self::RESET,
                self::BOLD . 'msg=' . $this->value($record->message) . self::RESET,
            ];

            foreach (array_merge($record->context, $record->extra) as $key => $val) {
                if ($val === null) {
                    continue;
                }
                $parts[] = self::CYAN . $key . '=' . self::RESET . $this->value($val);
            }
        } else {
            $parts = [
                'time=' . $this->value($time),
                'level=' . $levelName,
                'msg=' . $this->value($record->message),
            ];

            foreach (array_merge($record->context, $record->extra) as $key => $val) {
                if ($val === null) {
                    continue;
                }
                $parts[] = $key . '=' . $this->value($val);
            }
        }

        return implode(' ', $parts) . "\n";
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }

    private function value(mixed $val): string
    {
        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }

        if (is_array($val)) {
            $val = json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $str = (string) $val;

        if ($str === '' || str_contains($str, ' ') || str_contains($str, '=') || str_contains($str, '"')) {
            return '"' . addcslashes($str, '"\\') . '"';
        }

        return $str;
    }
}
