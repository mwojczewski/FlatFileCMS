<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Yaml;

use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use JsonException;

final readonly class YamlFileCache
{
    private const string CACHE_DIRECTORY = 'cache/yaml';

    public function __construct(
        private bool $enabled,
        private SafePathResolver $pathResolver,
        private AtomicFileWriter $fileWriter,
    ) {
        if ($this->enabled) {
            $cacheDirectory = $this->pathResolver->resolve(
                FilesystemRoot::Storage,
                RelativePath::fromString(self::CACHE_DIRECTORY),
            );

            if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0o700, true) && !is_dir($cacheDirectory)) {
                throw new FilesystemException('Unable to create the YAML cache directory.');
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function get(string $key, FileRevision $sourceRevision): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $relativePath = $this->cachePath($key);
        $cachePath = $this->pathResolver->resolve(FilesystemRoot::Storage, $relativePath);
        if (!is_file($cachePath)) {
            return null;
        }

        $contents = file_get_contents($cachePath);
        if ($contents === false) {
            return null;
        }

        try {
            $record = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            unlink($cachePath);

            return null;
        }

        if (!is_array($record) || ($record['revision'] ?? null) !== $sourceRevision->value()) {
            return null;
        }

        $data = $record['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        foreach (array_keys($data) as $dataKey) {
            if (!is_string($dataKey)) {
                return null;
            }
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** @param array<string, mixed> $data */
    public function put(string $key, FileRevision $sourceRevision, array $data): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $contents = json_encode(
                ['revision' => $sourceRevision->value(), 'data' => $data],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new FilesystemException('Unable to encode parsed YAML cache.', previous: $exception);
        }

        $this->fileWriter->write(FilesystemRoot::Storage, $this->cachePath($key), $contents);
    }

    public function clear(): void
    {
        if (!$this->enabled) {
            return;
        }

        $directory = $this->pathResolver->resolve(
            FilesystemRoot::Storage,
            RelativePath::fromString(self::CACHE_DIRECTORY),
        );
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.json');
        if ($files === false) {
            throw new FilesystemException('Unable to enumerate YAML cache files.');
        }

        foreach ($files as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new FilesystemException('Unable to remove a YAML cache file.');
            }
        }
    }

    private function cachePath(string $key): RelativePath
    {
        return RelativePath::fromString(self::CACHE_DIRECTORY . '/' . hash('sha256', $key) . '.json');
    }
}
