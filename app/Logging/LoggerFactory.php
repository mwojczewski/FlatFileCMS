<?php

declare(strict_types=1);

namespace FlatFileCms\Logging;

use FlatFileCms\Core\Environment;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final readonly class LoggerFactory
{
    public static function create(Environment $environment): LoggerInterface
    {
        $handler = new RotatingFileHandler(
            "{$environment->projectRoot()}/storage/logs/application.log",
            $environment->integer('LOG_MAX_FILES', 14),
            self::level($environment),
            true,
            0o640,
        );
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));

        return new Logger('flatfile-cms', [$handler]);
    }

    private static function level(Environment $environment): Level
    {
        return match (\strtolower($environment->get('LOG_LEVEL', $environment->debug() ? 'debug' : 'notice'))) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            default => throw new \RuntimeException('LOG_LEVEL is invalid.'),
        };
    }
}
