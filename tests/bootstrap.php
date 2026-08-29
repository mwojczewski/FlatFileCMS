<?php

declare(strict_types=1);

error_reporting(E_ALL);
set_error_handler(
    static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    },
    E_DEPRECATED | E_USER_DEPRECATED,
);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Dependencies are missing. Run composer install before the test suite.');
}

require $autoload;
