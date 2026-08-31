<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;

final readonly class MediaRepository
{
    public function __construct(
        private SafePathResolver $paths,
        private ConfigurationRepository $configuration,
        private MediaInspector $inspector,
    ) {}

    /** @return list<MediaItem> */
    public function all(PageIdentity $identity): array
    {
        $directory = $this->paths->resolve(
            FilesystemRoot::Pages,
            RelativePath::fromString($identity->value()),
            mustExist: true,
        );
        $entries = scandir($directory);
        if ($entries === false) {
            throw new MediaException('Page media directory cannot be read.');
        }

        $items = [];
        foreach ($entries as $entry) {
            try {
                $name = MediaName::fromString($entry);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $candidate = "{$directory}/{$entry}";
            if (!is_file($candidate) || is_link($candidate)) {
                continue;
            }
            try {
                $items[] = $this->get($identity, $name)->item();
            } catch (MediaException) {
                continue;
            }
        }
        usort($items, static fn(MediaItem $left, MediaItem $right): int => $right->modifiedAt() <=> $left->modifiedAt());

        return $items;
    }

    public function get(PageIdentity $identity, MediaName $name): MediaFile
    {
        $directory = $this->paths->resolve(
            FilesystemRoot::Pages,
            RelativePath::fromString($identity->value()),
            mustExist: true,
        );
        if (is_link("{$directory}/{$name->value()}")) {
            throw new MediaException('Media symlinks are not allowed.');
        }
        $path = $this->relativePath($identity, $name);
        $absolutePath = $this->paths->resolve(FilesystemRoot::Pages, $path, mustExist: true);
        if (!is_file($absolutePath) || is_link($absolutePath)) {
            throw new MediaException('Media file does not exist.');
        }
        $contents = file_get_contents($absolutePath);
        $size = filesize($absolutePath);
        $modifiedAt = filemtime($absolutePath);
        if ($contents === false || $size === false || $modifiedAt === false || $size !== \strlen($contents)) {
            throw new MediaException('Media file metadata cannot be read.');
        }
        $inspected = $this->inspector->inspect($contents, $name->value());
        $config = MediaConfig::fromDocument($this->configuration->get());
        if (!$config->allows($inspected['mimeType'])) {
            throw new MediaException('Media MIME type is not allowed by site configuration.');
        }

        $safeContents = $inspected['contents'];
        $item = new MediaItem(
            $name,
            $inspected['mimeType'],
            \strlen($safeContents),
            $modifiedAt,
            hash('sha256', $safeContents),
            $inspected['width'],
            $inspected['height'],
        );

        return new MediaFile($item, $safeContents);
    }

    public function modifiedAt(PageIdentity $identity): int
    {
        $directory = $this->paths->resolve(
            FilesystemRoot::Pages,
            RelativePath::fromString($identity->value()),
            mustExist: true,
        );
        $entries = scandir($directory);
        if ($entries === false) {
            throw new MediaException('Page media directory cannot be read.');
        }

        $modifiedAt = 0;
        foreach ($entries as $entry) {
            try {
                MediaName::fromString($entry);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $candidate = "{$directory}/{$entry}";
            if (!is_file($candidate) || is_link($candidate)) {
                continue;
            }
            $timestamp = filemtime($candidate);
            if ($timestamp === false) {
                throw new MediaException('Media file modification time cannot be read.');
            }
            $modifiedAt = max($modifiedAt, $timestamp);
        }

        return $modifiedAt;
    }

    public function relativePath(PageIdentity $identity, MediaName $name): RelativePath
    {
        return RelativePath::fromString("{$identity->value()}/{$name->value()}");
    }
}
