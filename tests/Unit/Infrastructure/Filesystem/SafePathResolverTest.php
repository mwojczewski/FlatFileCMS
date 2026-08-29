<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Infrastructure\Filesystem;

use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\PathEscapeException;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafePathResolver::class)]
final class SafePathResolverTest extends TestCase
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

    public function testItResolvesExistingAndFuturePathsInsideRoot(): void
    {
        $this->project->write('pages/offer/content.yml', 'enabled: true');
        $resolver = new SafePathResolver($this->project->path());

        self::assertSame(
            $this->project->path('pages/offer/content.yml'),
            $resolver->resolve(
                FilesystemRoot::Pages,
                RelativePath::fromString('offer/content.yml'),
                mustExist: true,
            ),
        );
        self::assertSame(
            $this->project->path('pages/future/content.yml'),
            $resolver->resolve(FilesystemRoot::Pages, RelativePath::fromString('future/content.yml')),
        );
    }

    public function testItRejectsSymbolicLinkEscapingRoot(): void
    {
        $externalDirectory = sys_get_temp_dir() . '/flatfile-cms-external-' . bin2hex(random_bytes(6));
        mkdir($externalDirectory, 0o700);
        file_put_contents($externalDirectory . '/secret.txt', 'secret');

        try {
            if (!symlink($externalDirectory, $this->project->path('pages/escape'))) {
                self::markTestSkipped('Symbolic links are unavailable.');
            }

            $resolver = new SafePathResolver($this->project->path());
            $this->expectException(PathEscapeException::class);
            $resolver->resolve(FilesystemRoot::Pages, RelativePath::fromString('escape/secret.txt'));
        } finally {
            unlink($externalDirectory . '/secret.txt');
            rmdir($externalDirectory);
        }
    }
}
