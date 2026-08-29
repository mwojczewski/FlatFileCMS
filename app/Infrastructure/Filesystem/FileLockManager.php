<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

use Closure;

final readonly class FileLockManager
{
    private string $lockDirectory;

    public function __construct(SafePathResolver $pathResolver)
    {
        $lockDirectory = $pathResolver->resolve(
            FilesystemRoot::Storage,
            RelativePath::fromString('tmp/locks'),
        );

        if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0o700, true) && !is_dir($lockDirectory)) {
            throw new FilesystemException('Unable to create the lock directory.');
        }

        $this->lockDirectory = $lockDirectory;
    }

    /**
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function exclusive(string $resourceKey, Closure $operation): mixed
    {
        $lockPath = $this->lockDirectory . DIRECTORY_SEPARATOR . hash('sha256', $resourceKey) . '.lock';
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new FilesystemException('Unable to open the filesystem lock.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new FilesystemException('Unable to acquire the filesystem lock.');
            }

            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
