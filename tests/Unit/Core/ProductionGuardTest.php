<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Core;

use FlatFileCms\Core\Environment;
use FlatFileCms\Core\ProductionGuard;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductionGuardTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItRejectsPlaceholderProductionSecret(): void
    {
        $this->project->write('.env.local', <<<'ENV'
APP_ENV=production
APP_DEBUG=0
APP_SECRET=CHANGE_ME_generate_at_least_32_random_bytes
APP_TIMEZONE=Europe/Warsaw
SESSION_COOKIE_SECURE=1
SESSION_COOKIE_SAME_SITE=Lax
ENV);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET');

        ProductionGuard::initialize(Environment::load($this->project->path()));
    }

    public function testItAcceptsHardenedProductionRuntime(): void
    {
        $this->project->write('.env.local', <<<'ENV'
APP_ENV=production
APP_DEBUG=0
APP_SECRET=0123456789abcdef0123456789abcdef
APP_TIMEZONE=Europe/Warsaw
SESSION_COOKIE_SECURE=1
SESSION_COOKIE_SAME_SITE=Strict
ENV);

        ProductionGuard::initialize(Environment::load($this->project->path()));

        self::assertSame('Europe/Warsaw', \date_default_timezone_get());
        \date_default_timezone_set('UTC');
    }
}
