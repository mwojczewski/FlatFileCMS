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
        $this->repository = $this->repository(jsonEnabled: true, serializeEnabled: false);
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

    public function testSerializedCacheWorksWithoutJsonCache(): void
    {
        $this->project->write('config/setup.yml', "site:\n  name: Serialized\n");
        $repository = $this->repository(jsonEnabled: false, serializeEnabled: true);

        $first = $repository->read(FilesystemRoot::Config, RelativePath::fromString('setup.yml'));
        $second = $repository->read(FilesystemRoot::Config, RelativePath::fromString('setup.yml'));

        self::assertSame($first->data(), $second->data());
        self::assertCount(1, $this->cacheFiles('serialized'));
        self::assertSame([], $this->cacheFiles('json'));
    }

    public function testSourceRevisionInvalidatesSerializedCache(): void
    {
        $this->project->write('config/setup.yml', "site:\n  name: Before\n");
        $path = RelativePath::fromString('setup.yml');
        $repository = $this->repository(jsonEnabled: false, serializeEnabled: true);

        $first = $repository->read(FilesystemRoot::Config, $path);
        $this->project->write('config/setup.yml', "site:\n  name: After\n");
        $second = $repository->read(FilesystemRoot::Config, $path);

        self::assertSame(['name' => 'Before'], $first->data()['site']);
        self::assertSame(['name' => 'After'], $second->data()['site']);
        self::assertFalse($first->revision()->equals($second->revision()));
    }

    public function testBothCacheFormatsAreWrittenAndClearedIndependently(): void
    {
        $resolver = new SafePathResolver($this->project->path());
        $writer = new AtomicFileWriter($resolver, new FileLockManager($resolver));
        $cache = new YamlFileCache(true, $resolver, $writer, true);
        $revision = FileRevision::fromContents('source');
        $data = ['site' => ['name' => 'Both']];
        $cache->put('config:setup.yml', $revision, $data);

        self::assertCount(1, $this->cacheFiles('json'));
        self::assertCount(1, $this->cacheFiles('serialized'));

        unlink($this->cacheFiles('serialized')[0]);
        self::assertSame($data, $cache->get('config:setup.yml', $revision));
        self::assertCount(1, $this->cacheFiles('serialized'));

        unlink($this->cacheFiles('json')[0]);
        self::assertSame($data, $cache->get('config:setup.yml', $revision));
        self::assertCount(1, $this->cacheFiles('json'));

        $cache->clear();

        self::assertSame([], $this->cacheFiles('json'));
        self::assertSame([], $this->cacheFiles('serialized'));
    }

    public function testSerializedCacheNeverRestoresObjects(): void
    {
        $resolver = new SafePathResolver($this->project->path());
        $writer = new AtomicFileWriter($resolver, new FileLockManager($resolver));
        $cache = new YamlFileCache(false, $resolver, $writer, true);
        $revision = FileRevision::fromContents('source');
        $path = $this->project->path('storage/cache/yaml/' . hash('sha256', 'config:setup.yml') . '.serialized');
        file_put_contents($path, serialize([
            'revision' => $revision->value(),
            'data' => ['unsafe' => new stdClass()],
        ]));

        self::assertNull($cache->get('config:setup.yml', $revision));
        self::assertFileDoesNotExist($path);
    }

    private function repository(bool $jsonEnabled, bool $serializeEnabled): YamlFileRepository
    {
        $resolver = new SafePathResolver($this->project->path());
        $writer = new AtomicFileWriter($resolver, new FileLockManager($resolver));

        return new YamlFileRepository(
            $resolver,
            new YamlParser(),
            new YamlFileCache($jsonEnabled, $resolver, $writer, $serializeEnabled),
            $writer,
        );
    }

    /** @return list<string> */
    private function cacheFiles(string $extension): array
    {
        $files = glob($this->project->path("storage/cache/yaml/*.{$extension}"));

        return $files === false ? [] : $files;
    }
}
