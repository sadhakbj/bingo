<?php

declare(strict_types=1);

namespace Config;

use Bingo\Attributes\Config\Env;

final readonly class LogConfig
{
    public function __construct(
        #[Env('LOG_CHANNEL', default: 'stack')] public string $channel,

        #[Env('LOG_LEVEL', default: 'debug')] public string $level,

        #[Env('LOG_PATH', default: 'storage/logs/bingo.log')] public string $path,

        #[Env('LOG_STDERR_LEVEL', default: 'error')] public string $stderrLevel,

        #[Env('LOG_FORMAT', default: 'text')] public string $format,

        #[Env('LOG_TIME_FORMAT', default: 'Y-m-d\TH:i:sP')] public string $timeFormat,
    ) {}
}
