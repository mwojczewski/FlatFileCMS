<?php

declare(strict_types=1);

namespace FlatFileCms\Core;

use DateTimeZone;
use RuntimeException;

final class ProductionGuard
{
    public static function initialize(Environment $environment): void
    {
        $name = $environment->name();
        if (!\in_array($name, ['development', 'testing', 'production'], true)) {
            throw new RuntimeException('APP_ENV must be development, testing or production.');
        }
        $timezone = $environment->get('APP_TIMEZONE', 'UTC');
        if (!\in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException('APP_TIMEZONE is invalid.');
        }
        \date_default_timezone_set($timezone);

        if ($name === 'production') {
            self::assertProduction($environment);
        }
    }

    public static function assertProduction(Environment $environment): void
    {
        if ($environment->name() !== 'production') {
            throw new RuntimeException('APP_ENV must be production for a release check.');
        }
        $timezone = $environment->get('APP_TIMEZONE', 'UTC');
        if (!\in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException('APP_TIMEZONE is invalid.');
        }
        $secret = $environment->get('APP_SECRET');
        if (\strlen($secret) < 32 || \str_contains(\strtoupper($secret), 'CHANGE_ME')) {
            throw new RuntimeException('APP_SECRET must contain at least 32 non-placeholder bytes.');
        }
        if ($environment->debug()) {
            throw new RuntimeException('APP_DEBUG must be disabled in production.');
        }
        if (!$environment->boolean('SESSION_COOKIE_SECURE', true)) {
            throw new RuntimeException('SESSION_COOKIE_SECURE must be enabled in production.');
        }
        if (!\in_array($environment->get('SESSION_COOKIE_SAME_SITE', 'Lax'), ['Lax', 'Strict'], true)) {
            throw new RuntimeException('SESSION_COOKIE_SAME_SITE must be Lax or Strict.');
        }
    }
}
