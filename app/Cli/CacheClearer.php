<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use FilesystemIterator;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class CacheClearer
{
    private string $cacheRoot;

    public function __construct(SafePathResolver $paths)
    {
        $cacheRoot = $paths->rootPath(FilesystemRoot::Storage) . DIRECTORY_SEPARATOR . 'cache';
        if (is_link($cacheRoot)) {
            throw new FilesystemException('Cache root cannot be a symbolic link.');
        }
        if (!is_dir($cacheRoot) && !mkdir($cacheRoot, 0o700) && !is_dir($cacheRoot)) {
            throw new FilesystemException('Unable to create the cache directory.');
        }

        $this->cacheRoot = $paths->resolve(
            FilesystemRoot::Storage,
            RelativePath::fromString('cache'),
            mustExist: true,
        );
    }

    public function clear(): int
    {
        $removed = 0;
        $preservedFile = $this->cacheRoot . DIRECTORY_SEPARATOR . '.gitkeep';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($path === $preservedFile) {
                continue;
            }

            $removed += $this->remove($item);
        }

        return $removed;
    }

    private function remove(SplFileInfo $item): int
    {
        $path = $item->getPathname();
        $removed = $item->isLink() || $item->isFile() ? unlink($path) : rmdir($path);
        if (!$removed) {
            throw new FilesystemException('Unable to remove a cache entry.');
        }

        return 1;
    }
}
