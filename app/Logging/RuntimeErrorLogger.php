<?php

declare(strict_types=1);

namespace FlatFileCms\Logging;

use Psr\Log\LoggerInterface;

final class RuntimeErrorLogger
{
    private function __construct() {}

    public static function register(LoggerInterface $logger): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logger): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            $logger->warning('PHP runtime warning', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);

            return false;
        });

        register_shutdown_function(static function () use ($logger): void {
            $error = error_get_last();
            if ($error === null || !\in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $logger->critical('Fatal PHP error', [
                'severity' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
        });
    }
}
