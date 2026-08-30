<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class DirectoryOperator
{
    public function __construct(private SafePathResolver $paths) {}

    public function create(FilesystemRoot $root, RelativePath $path): void
    {
        $this->assertDirectoryPath($path);
        $absolutePath = $this->paths->resolve($root, $path);
        if (file_exists($absolutePath) || is_link($absolutePath)) {
            throw new FilesystemException('Destination directory already exists.');
        }
        $parent = \dirname($absolutePath);
        if (!is_dir($parent) || is_link($parent)) {
            throw new FilesystemException('Destination parent directory does not exist.');
        }
        if (!mkdir($absolutePath, 0o750)) {
            throw new FilesystemException('Unable to create directory.');
        }
    }

    public function move(FilesystemRoot $root, RelativePath $source, RelativePath $destination): void
    {
        $this->assertDirectoryPath($source);
        $this->assertDirectoryPath($destination);
        $sourcePath = $this->paths->resolve($root, $source, mustExist: true);
        $destinationPath = $this->paths->resolve($root, $destination);
        if (!is_dir($sourcePath) || is_link($sourcePath)) {
            throw new FilesystemException('Source is not a movable directory.');
        }
        if (file_exists($destinationPath) || is_link($destinationPath)) {
            throw new FilesystemException('Destination directory already exists.');
        }
        if (!is_dir(\dirname($destinationPath)) || is_link(\dirname($destinationPath))) {
            throw new FilesystemException('Destination parent directory does not exist.');
        }
        if (!rename($sourcePath, $destinationPath)) {
            throw new FilesystemException('Unable to move directory atomically.');
        }
    }

    public function delete(FilesystemRoot $root, RelativePath $path): void
    {
        $this->assertDirectoryPath($path);
        $absolutePath = $this->paths->resolve($root, $path, mustExist: true);
        if (!is_dir($absolutePath) || is_link($absolutePath)) {
            throw new FilesystemException('Deletion target is not a directory.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                if (!unlink($item->getPathname())) {
                    throw new FilesystemException('Unable to delete page file.');
                }

                continue;
            }
            if (!rmdir($item->getPathname())) {
                throw new FilesystemException('Unable to delete page directory.');
            }
        }
        if (!rmdir($absolutePath)) {
            throw new FilesystemException('Unable to delete page directory.');
        }
    }

    private function assertDirectoryPath(RelativePath $path): void
    {
        if ($path->isRoot()) {
            throw new FilesystemException('Filesystem root cannot be used as a directory operation target.');
        }
    }
}
