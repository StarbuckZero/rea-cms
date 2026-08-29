<?php

declare(strict_types=1);

namespace ReaCms\Core\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(string $logFile, string $level): LoggerInterface
    {
        $logLevel = match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Warning,
        };
        $handler = new StreamHandler($logFile, $logLevel);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));

        return new Logger('rea-cms', [$handler]);
    }
}
