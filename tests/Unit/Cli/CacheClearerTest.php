<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Cli;

use FlatFileCms\Cli\CacheClearer;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheClearer::class)]
final class CacheClearerTest extends TestCase
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

    public function testItClearsEveryCacheTypeAndPreservesTheRootDirectory(): void
    {
        $this->project->write('storage/cache/.gitkeep', '');
        $this->project->write('storage/cache/yaml/page.json', '{}');
        $this->project->write('storage/cache/yaml/page.serialized', 'a:0:{}');
        $this->project->write('storage/cache/media/hero.webp', 'image');

        $clearer = new CacheClearer(new SafePathResolver($this->project->path()));

        self::assertSame(5, $clearer->clear());
        self::assertDirectoryExists($this->project->path('storage/cache'));
        self::assertFileExists($this->project->path('storage/cache/.gitkeep'));
        self::assertSame(
            [],
            array_values(array_diff(
                scandir($this->project->path('storage/cache')) ?: [],
                ['.', '..', '.gitkeep'],
            )),
        );
        self::assertSame(0, $clearer->clear());
    }

    public function testItUnlinksCacheSymlinksWithoutDeletingTheirTargets(): void
    {
        $this->project->write('storage/tmp/outside.txt', 'keep');
        $link = $this->project->path('storage/cache/outside-link');
        if (!symlink($this->project->path('storage/tmp/outside.txt'), $link)) {
            self::markTestSkipped('Symbolic links are unavailable on this platform.');
        }

        $clearer = new CacheClearer(new SafePathResolver($this->project->path()));

        self::assertSame(1, $clearer->clear());
        self::assertFileDoesNotExist($link);
        self::assertFileExists($this->project->path('storage/tmp/outside.txt'));
    }
}
