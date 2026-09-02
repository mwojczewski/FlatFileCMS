<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Content\PageBlockManager;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Http\UploadedFile;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use RuntimeException;

final readonly class MediaManager
{
    public function __construct(
        private MediaRepository $media,
        private PageBlockManager $pages,
        private ConfigurationRepository $configuration,
        private MediaInspector $inspector,
        private RasterImageProcessor $images,
        private SafePathResolver $paths,
        private AtomicFileWriter $writer,
        private FileLockManager $locks,
    ) {}

    public function upload(PageIdentity $identity, UploadedFile $upload): MediaItem
    {
        $this->pages->editable($identity);
        $config = MediaConfig::fromDocument($this->configuration->get());
        try {
            $contents = $upload->contents($config->maximumUploadBytes());
        } catch (RuntimeException $exception) {
            throw new MediaException($exception->getMessage(), previous: $exception);
        }
        $inspected = $this->inspector->inspect($contents, $upload->clientFilename());
        $mimeType = $inspected['mimeType'];
        if (!$config->allows($mimeType)) {
            throw new MediaException('Uploaded media MIME type is not allowed.');
        }
        if (
            $inspected['width'] !== null
            && $inspected['height'] !== null
            && $inspected['height'] > 0
            && $inspected['width'] > intdiv($config->maximumPixels(), $inspected['height'])
        ) {
            throw new MediaException('Uploaded image pixel count exceeds the configured limit.');
        }
        $contents = $inspected['contents'];
        if ($config->stripMetadata()) {
            $contents = $this->images->stripMetadata($contents, $mimeType, $config->quality());
        }
        $baseName = MediaName::fromUpload($upload->clientFilename(), $mimeType);

        return $this->locks->exclusive('page-media:' . $identity->value(), function () use ($identity, $baseName, $contents): MediaItem {
            $name = $this->availableName($identity, $baseName);
            $this->writer->write(
                FilesystemRoot::Pages,
                $this->media->relativePath($identity, $name),
                $contents,
                FileRevision::missing(),
            );

            return $this->media->get($identity, $name)->item();
        });
    }

    public function delete(PageIdentity $identity, MediaName $name): void
    {
        $this->locks->exclusive('page-media:' . $identity->value(), function () use ($identity, $name): void {
            $editable = $this->pages->editable($identity);
            if ($this->containsReference($editable->data(), $name->value())) {
                throw new MediaException('Media file is still referenced by page content.');
            }
            $sourceHash = $this->media->get($identity, $name)->item()->hash();
            $path = $this->paths->resolve(
                FilesystemRoot::Pages,
                $this->media->relativePath($identity, $name),
                mustExist: true,
            );
            $this->locks->exclusive('media-variants:' . $sourceHash, function () use ($path, $sourceHash): void {
                $this->removeVariantCache($sourceHash);
                if (!is_file($path) || is_link($path) || !unlink($path)) {
                    throw new MediaException('Media file could not be deleted.');
                }
            });
        });
    }

    private function removeVariantCache(string $sourceHash): void
    {
        $directory = $this->paths->resolve(
            FilesystemRoot::Storage,
            RelativePath::fromString(
                'cache/media/' . substr($sourceHash, 0, 2) . "/{$sourceHash}",
            ),
        );
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }
        $entries = scandir($directory);
        if ($entries === false) {
            throw new MediaException('Media variant cache could not be read.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $candidate = "{$directory}/{$entry}";
            if (is_link($candidate) || !is_file($candidate) || !unlink($candidate)) {
                throw new MediaException('Media variant cache could not be removed.');
            }
        }
        if (!rmdir($directory)) {
            throw new MediaException('Media variant cache directory could not be removed.');
        }
    }

    private function availableName(PageIdentity $identity, MediaName $base): MediaName
    {
        $extension = pathinfo($base->value(), PATHINFO_EXTENSION);
        $stem = pathinfo($base->value(), PATHINFO_FILENAME);
        for ($suffix = 1; $suffix <= 999; ++$suffix) {
            $candidate = MediaName::fromString($suffix === 1 ? $base->value() : "{$stem}-{$suffix}.{$extension}");
            $path = $this->paths->resolve(FilesystemRoot::Pages, $this->media->relativePath($identity, $candidate));
            if (!file_exists($path) && !is_link($path)) {
                return $candidate;
            }
        }

        throw new MediaException('Unable to allocate a unique media filename.');
    }

    private function containsReference(mixed $value, string $filename): bool
    {
        if (!\is_array($value)) {
            return false;
        }
        if (($value['src'] ?? null) === $filename) {
            return true;
        }
        foreach ($value as $child) {
            if ($this->containsReference($child, $filename)) {
                return true;
            }
        }

        return false;
    }
}
