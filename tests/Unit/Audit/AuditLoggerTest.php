<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Audit;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLogger::class)]
final class AuditLoggerTest extends TestCase
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

    public function testItAppendsStructuredEntriesToTheDailyJsonlFile(): void
    {
        $logger = new AuditLogger(new SafePathResolver($this->project->path()));
        $logger->log('page.updated', 7, 'pages/oferta', '192.0.2.10', ['revision' => 'abc']);
        $logger->log('auth.logout', 7, 'auth/session', '192.0.2.10');

        $files = glob($this->project->path('storage/audit/*.jsonl'));
        self::assertIsArray($files);
        self::assertCount(1, $files);
        $lines = file($files[0] ?? throw new \RuntimeException('Audit file is missing.'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        self::assertCount(2, $lines);

        /** @var array<string, mixed> $entry */
        $entry = json_decode($lines[0] ?? throw new \RuntimeException('Audit entry is missing.'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('page.updated', $entry['action'] ?? null);
        self::assertSame(7, $entry['user_id'] ?? null);
        self::assertSame('pages/oferta', $entry['resource'] ?? null);
        self::assertSame(['revision' => 'abc'], $entry['metadata'] ?? null);
        self::assertIsString($entry['timestamp'] ?? null);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $entry['timestamp']);
    }
}
