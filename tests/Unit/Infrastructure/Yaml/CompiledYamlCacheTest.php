<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Infrastructure\Yaml;

use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\CompiledYamlCache;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompiledYamlCache::class)]
final class CompiledYamlCacheTest extends TestCase
{
    private TemporaryProject $project;
    private SafePathResolver $paths;
    private AtomicFileWriter $writer;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('errors/.gitkeep', '');
        $this->paths = new SafePathResolver($this->project->path());
        $this->writer = new AtomicFileWriter($this->paths, new FileLockManager($this->paths));
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testWarmupBuildsReleaseScopedPhpCache(): void
    {
        $this->project->write('config/setup.yml', "schemaVersion: 1\nsite: { name: Test }\n");
        $cache = $this->cache('release-1');

        self::assertSame(1, $cache->warmUp());
        $document = $cache->get('config:setup.yml');

        self::assertNotNull($document);
        $site = ContentData::map($document->data()['site'] ?? null, 'site');
        self::assertSame('Test', $site['name'] ?? null);
    }

    public function testDifferentReleaseRejectsStaleCache(): void
    {
        $this->project->write('config/setup.yml', "schemaVersion: 1\n");
        $this->cache('release-1')->warmUp();

        $this->expectException(FilesystemException::class);
        $this->cache('release-2')->get('config:setup.yml');
    }

    private function cache(string $release): CompiledYamlCache
    {
        return new CompiledYamlCache(true, $release, $this->paths, $this->writer, new YamlParser());
    }
}
