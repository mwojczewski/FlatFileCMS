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
use Throwable;

final readonly class YamlFileCache
{
    private const string CACHE_DIRECTORY = 'cache/yaml';

    public function __construct(
        private bool $jsonEnabled,
        private SafePathResolver $pathResolver,
        private AtomicFileWriter $fileWriter,
        private bool $serializeEnabled = false,
    ) {
        if ($this->jsonEnabled || $this->serializeEnabled) {
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
        if (!$this->jsonEnabled && !$this->serializeEnabled) {
            return null;
        }

        if ($this->serializeEnabled) {
            $data = $this->getSerialized($key, $sourceRevision);
            if ($data !== null) {
                if ($this->jsonEnabled && !$this->hasCacheFile($key, 'json')) {
                    $this->putJson($key, $sourceRevision, $data);
                }

                return $data;
            }
        }

        if (!$this->jsonEnabled) {
            return null;
        }

        $data = $this->getJson($key, $sourceRevision);
        if ($data !== null && $this->serializeEnabled && !$this->hasCacheFile($key, 'serialized')) {
            $this->putSerialized($key, $sourceRevision, $data);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function put(string $key, FileRevision $sourceRevision, array $data): void
    {
        if ($this->jsonEnabled) {
            $this->putJson($key, $sourceRevision, $data);
        }
        if ($this->serializeEnabled) {
            $this->putSerialized($key, $sourceRevision, $data);
        }
    }

    public function clear(): void
    {
        if (!$this->jsonEnabled && !$this->serializeEnabled) {
            return;
        }

        $directory = $this->pathResolver->resolve(
            FilesystemRoot::Storage,
            RelativePath::fromString(self::CACHE_DIRECTORY),
        );
        $extensions = [];
        if ($this->jsonEnabled) {
            $extensions[] = 'json';
        }
        if ($this->serializeEnabled) {
            $extensions[] = 'serialized';
        }
        foreach ($extensions as $extension) {
            $files = glob($directory . DIRECTORY_SEPARATOR . '*.' . $extension);
            if ($files === false) {
                throw new FilesystemException('Unable to enumerate YAML cache files.');
            }

            foreach ($files as $file) {
                if (is_file($file) && !unlink($file)) {
                    throw new FilesystemException('Unable to remove a YAML cache file.');
                }
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function getJson(string $key, FileRevision $sourceRevision): ?array
    {
        $relativePath = $this->cachePath($key, 'json');
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

        return $this->recordData($record, $sourceRevision);
    }

    /** @param array<string, mixed> $data */
    private function putJson(string $key, FileRevision $sourceRevision, array $data): void
    {
        try {
            $contents = json_encode(
                ['revision' => $sourceRevision->value(), 'data' => $data],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new FilesystemException('Unable to encode parsed YAML cache.', previous: $exception);
        }

        $this->fileWriter->write(FilesystemRoot::Storage, $this->cachePath($key, 'json'), $contents);
    }

    /** @return array<string, mixed>|null */
    private function getSerialized(string $key, FileRevision $sourceRevision): ?array
    {
        $relativePath = $this->cachePath($key, 'serialized');
        $cachePath = $this->pathResolver->resolve(FilesystemRoot::Storage, $relativePath);
        if (!is_file($cachePath)) {
            return null;
        }
        $contents = file_get_contents($cachePath);
        if ($contents === false) {
            return null;
        }

        $record = @unserialize($contents, ['allowed_classes' => false, 'max_depth' => 64]);
        $data = $this->recordData($record, $sourceRevision);
        if ($data === null) {
            unlink($cachePath);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function putSerialized(string $key, FileRevision $sourceRevision, array $data): void
    {
        try {
            $contents = serialize(['revision' => $sourceRevision->value(), 'data' => $data]);
        } catch (Throwable $exception) {
            throw new FilesystemException('Unable to serialize parsed YAML cache.', previous: $exception);
        }

        $this->fileWriter->write(
            FilesystemRoot::Storage,
            $this->cachePath($key, 'serialized'),
            $contents,
        );
    }

    /** @return array<string, mixed>|null */
    private function recordData(mixed $record, FileRevision $sourceRevision): ?array
    {
        if (!\is_array($record) || ($record['revision'] ?? null) !== $sourceRevision->value()) {
            return null;
        }

        $data = $record['data'] ?? null;
        if (!\is_array($data) || !$this->validData($data, 0)) {
            return null;
        }

        foreach (array_keys($data) as $dataKey) {
            if (!\is_string($dataKey)) {
                return null;
            }
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** @param array<mixed> $data */
    private function validData(array $data, int $depth): bool
    {
        if ($depth > 64) {
            return false;
        }
        foreach ($data as $value) {
            if (\is_array($value)) {
                if (!$this->validData($value, $depth + 1)) {
                    return false;
                }

                continue;
            }
            if (!\is_string($value) && !\is_int($value) && !\is_float($value) && !\is_bool($value) && $value !== null) {
                return false;
            }
        }

        return true;
    }

    private function hasCacheFile(string $key, string $extension): bool
    {
        $path = $this->pathResolver->resolve(
            FilesystemRoot::Storage,
            $this->cachePath($key, $extension),
        );

        return is_file($path);
    }

    private function cachePath(string $key, string $extension): RelativePath
    {
        return RelativePath::fromString(
            self::CACHE_DIRECTORY . '/' . hash('sha256', $key) . '.' . $extension,
        );
    }
}
