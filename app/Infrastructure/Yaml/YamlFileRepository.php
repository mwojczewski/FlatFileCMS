<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Yaml;

use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlFileRepository
{
    public function __construct(
        private SafePathResolver $pathResolver,
        private YamlParser $parser,
        private YamlFileCache $cache,
        private AtomicFileWriter $fileWriter,
        private int $maxBytes = 1_048_576,
    ) {
        if ($this->maxBytes < 1) {
            throw new InvalidYamlException('YAML repository size limit must be positive.');
        }
    }

    public function read(FilesystemRoot $root, RelativePath $relativePath): YamlDocument
    {
        $this->assertYamlPath($relativePath);
        $absolutePath = $this->pathResolver->resolve($root, $relativePath, mustExist: true);
        if (!is_file($absolutePath)) {
            throw new FilesystemException('YAML path does not reference a regular file.');
        }

        $size = filesize($absolutePath);
        if ($size === false || $size > $this->maxBytes) {
            throw new InvalidYamlException('YAML file exceeds the configured size limit.');
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new FilesystemException('Unable to read YAML file.');
        }

        $revision = FileRevision::fromContents($contents);
        $cacheKey = $root->value . ':' . $relativePath->value();
        $cached = $this->cache->get($cacheKey, $revision);
        if ($cached !== null) {
            return new YamlDocument($cached, $revision);
        }

        $data = $this->parser->parse($contents);
        $this->cache->put($cacheKey, $revision, $data);

        return new YamlDocument($data, $revision);
    }

    /** @param array<string, mixed> $data */
    public function write(
        FilesystemRoot $root,
        RelativePath $relativePath,
        array $data,
        FileRevision $expectedRevision,
    ): YamlDocument {
        $this->assertYamlPath($relativePath);
        try {
            $contents = Yaml::dump(
                $data,
                12,
                2,
                Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
            );
        } catch (DumpException $exception) {
            throw new InvalidYamlException('YAML data cannot be serialized.', previous: $exception);
        }

        // Validate the exact serialized representation before it can replace
        // the destination file.
        $normalizedData = $this->parser->parse($contents);
        $revision = $this->fileWriter->write($root, $relativePath, $contents, $expectedRevision);
        $cacheKey = "{$root->value}:" . $relativePath->value();
        $this->cache->put($cacheKey, $revision, $normalizedData);

        return new YamlDocument($normalizedData, $revision);
    }

    private function assertYamlPath(RelativePath $relativePath): void
    {
        if ($relativePath->isRoot() || preg_match('/\.ya?ml$/D', $relativePath->value()) !== 1) {
            throw new InvalidYamlException('YAML repository accepts only .yml and .yaml files.');
        }
    }
}
