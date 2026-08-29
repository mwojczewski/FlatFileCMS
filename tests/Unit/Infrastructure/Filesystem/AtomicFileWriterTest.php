<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Infrastructure\Filesystem;

use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AtomicFileWriter::class)]
#[CoversClass(FileLockManager::class)]
#[CoversClass(FileRevision::class)]
final class AtomicFileWriterTest extends TestCase
{
    private TemporaryProject $project;
    private AtomicFileWriter $writer;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $resolver = new SafePathResolver($this->project->path());
        $this->writer = new AtomicFileWriter($resolver, new FileLockManager($resolver));
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItCreatesAndAtomicallyReplacesAFile(): void
    {
        $path = RelativePath::fromString('offer/content.yml');
        $createdRevision = $this->writer->write(
            FilesystemRoot::Pages,
            $path,
            "enabled: true\n",
            FileRevision::missing(),
        );

        self::assertSame("enabled: true\n", file_get_contents($this->project->path('pages/offer/content.yml')));

        $updatedRevision = $this->writer->write(
            FilesystemRoot::Pages,
            $path,
            "enabled: false\n",
            $createdRevision,
        );

        self::assertNotSame($createdRevision->value(), $updatedRevision->value());
        self::assertSame("enabled: false\n", file_get_contents($this->project->path('pages/offer/content.yml')));
    }

    public function testRevisionConflictDoesNotOverwriteNewerContents(): void
    {
        $path = RelativePath::fromString('offer/content.yml');
        $originalRevision = $this->writer->write(
            FilesystemRoot::Pages,
            $path,
            "version: 1\n",
            FileRevision::missing(),
        );
        $this->writer->write(FilesystemRoot::Pages, $path, "version: 2\n", $originalRevision);

        try {
            $this->writer->write(FilesystemRoot::Pages, $path, "version: stale\n", $originalRevision);
            self::fail('Expected a revision conflict.');
        } catch (RevisionConflictException $exception) {
            self::assertTrue($exception->expected()->equals($originalRevision));
        }

        self::assertSame("version: 2\n", file_get_contents($this->project->path('pages/offer/content.yml')));
    }
}
