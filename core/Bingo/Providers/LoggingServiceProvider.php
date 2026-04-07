<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Application;
use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Attributes\Provider\Singleton;
use Bingo\Log\RequestContextProcessor;
use Bingo\Log\SlogTextFormatter;
use Config\LogConfig;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

#[ServiceProvider]
class LoggingServiceProvider
{
    #[Singleton]
    public function requestContextProcessor(): RequestContextProcessor
    {
        return new RequestContextProcessor();
    }

    #[Singleton]
    public function logger(
        Application $app,
        LogConfig $cfg,
        RequestContextProcessor $processor,
    ): LoggerInterface
    {
        $logger = new Logger('bingo');

        $level       = Level::fromName(ucfirst($cfg->level));
        $stderrLevel = Level::fromName(ucfirst($cfg->stderrLevel));

        $stderrHandler = new StreamHandler('php://stderr', $stderrLevel);
        $fileHandler   = new RotatingFileHandler($app->resolvePath($cfg->path), 30, $level);

        $isTerminal = defined('STDERR') && stream_isatty(\STDERR);

        $stderrHandler->setFormatter(match ($cfg->format) {
            'json'  => new JsonFormatter(),
            default => new SlogTextFormatter(colors: $isTerminal, timeFormat: $cfg->timeFormat),
        });
        $fileHandler->setFormatter(match ($cfg->format) {
            'json'  => new JsonFormatter(),
            default => new SlogTextFormatter(colors: false, timeFormat: $cfg->timeFormat),
        });

        $logger->pushHandler($fileHandler);
        $logger->pushHandler($stderrHandler);
        $logger->pushProcessor($processor);

        return $logger;
    }
}
