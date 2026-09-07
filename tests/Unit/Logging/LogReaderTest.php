<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Logging;

use FlatFileCms\Logging\LogReader;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogReader::class)]
final class LogReaderTest extends TestCase
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

    public function testReadsFiltersAndNormalizesJsonLogs(): void
    {
        $this->project->write('storage/logs/application-2026-09-07.log', implode("\n", [
            json_encode(['message' => 'Page saved', 'level_name' => 'INFO', 'datetime' => '2026-09-07T10:15:00+02:00', 'context' => ['page' => 'home']]),
            json_encode(['message' => 'Database failed', 'level_name' => 'ERROR', 'datetime' => '2026-09-07T10:16:00+02:00', 'context' => ['code' => 5]]),
            '{invalid',
        ]));

        $result = (new LogReader($this->project->path()))->read('application-2026-09-07.log', 'error', 'database');

        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['malformed']);
        self::assertSame('ERROR', $result['entries'][0]['level']);
        self::assertSame('07.09.2026, 10:16:00', $result['entries'][0]['date']);
        self::assertSame(['code' => 5], $result['entries'][0]['context']);
    }

    public function testIgnoresUnrelatedFilesAndRejectsUnknownSelection(): void
    {
        $this->project->write('storage/logs/application.log', "{}\n");
        $this->project->write('storage/logs/secret.log', "{}\n");
        $reader = new LogReader($this->project->path());

        self::assertSame(['application.log'], array_column($reader->files(), 'name'));

        $this->expectException(\InvalidArgumentException::class);
        $reader->read('../.env');
    }
}
