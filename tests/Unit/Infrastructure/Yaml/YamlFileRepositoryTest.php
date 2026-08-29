<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Infrastructure\Yaml;

use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\InvalidYamlException;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(YamlFileRepository::class)]
#[CoversClass(YamlFileCache::class)]
final class YamlFileRepositoryTest extends TestCase
{
    private TemporaryProject $project;
    private YamlFileRepository $repository;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $resolver = new SafePathResolver($this->project->path());
        $writer = new AtomicFileWriter($resolver, new FileLockManager($resolver));
        $this->repository = new YamlFileRepository(
            $resolver,
            new YamlParser(),
            new YamlFileCache(true, $resolver, $writer),
            $writer,
        );
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItWritesAndReadsValidatedYamlWithRevision(): void
    {
        $path = RelativePath::fromString('homepage/content.yml');
        $written = $this->repository->write(
            FilesystemRoot::Pages,
            $path,
            ['enabled' => true, 'title' => ['pl' => 'Start']],
            FileRevision::missing(),
        );
        $read = $this->repository->read(FilesystemRoot::Pages, $path);

        self::assertSame($written->data(), $read->data());
        self::assertTrue($written->revision()->equals($read->revision()));
    }

    public function testContentHashInvalidatesCachedParsedData(): void
    {
        $this->project->write('config/setup.yml', "site:\n  name: Before\n");
        $path = RelativePath::fromString('setup.yml');

        $first = $this->repository->read(FilesystemRoot::Config, $path);
        $this->project->write('config/setup.yml', "site:\n  name: After!\n");
        $second = $this->repository->read(FilesystemRoot::Config, $path);

        self::assertSame(['name' => 'Before'], $first->data()['site']);
        self::assertSame(['name' => 'After!'], $second->data()['site']);
        self::assertFalse($first->revision()->equals($second->revision()));
    }

    public function testInvalidTypeCannotCreateDestinationFile(): void
    {
        $path = RelativePath::fromString('invalid/content.yml');

        try {
            $this->repository->write(
                FilesystemRoot::Pages,
                $path,
                ['unsupported' => new stdClass()],
                FileRevision::missing(),
            );
            self::fail('Expected invalid YAML data to be rejected.');
        } catch (InvalidYamlException) {
            self::assertFileDoesNotExist($this->project->path('pages/invalid/content.yml'));
        }
    }
}
