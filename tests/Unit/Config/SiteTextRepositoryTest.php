<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Config;

use FlatFileCms\Config\SiteTextRepository;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class SiteTextRepositoryTest extends TestCase
{
    private TemporaryProject $project;
    private SiteTextRepository $repository;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $paths = new SafePathResolver($this->project->path());
        $this->repository = new SiteTextRepository($paths, new AtomicFileWriter($paths, new FileLockManager($paths)));
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItCreatesAndUpdatesPublicTextFilesAtomically(): void
    {
        $missing = $this->repository->llms();
        self::assertSame('missing', $missing->revision()->value());

        $written = $this->repository->updateLlms("# Example\r\n", $missing->revision());
        self::assertSame("# Example\n", $written->contents());
        self::assertSame("# Example\n", $this->repository->llms()->contents());
    }
}
