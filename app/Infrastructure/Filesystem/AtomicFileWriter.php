<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

final readonly class AtomicFileWriter
{
    public function __construct(
        private SafePathResolver $pathResolver,
        private FileLockManager $lockManager,
    ) {}

    public function write(
        FilesystemRoot $root,
        RelativePath $relativePath,
        string $contents,
        ?FileRevision $expectedRevision = null,
    ): FileRevision {
        if ($relativePath->isRoot()) {
            throw new FilesystemException('Cannot write to a filesystem root.');
        }

        $resourceKey = $root->value . ':' . $relativePath->value();

        return $this->lockManager->exclusive(
            $resourceKey,
            fn(): FileRevision => $this->writeUnderLock($root, $relativePath, $contents, $expectedRevision),
        );
    }

    public function revision(FilesystemRoot $root, RelativePath $relativePath): FileRevision
    {
        $targetPath = $this->pathResolver->resolve($root, $relativePath);
        if (!file_exists($targetPath)) {
            return FileRevision::missing();
        }

        if (!is_file($targetPath)) {
            throw new FilesystemException('Revision target is not a regular file.');
        }

        $contents = file_get_contents($targetPath);
        if ($contents === false) {
            throw new FilesystemException('Unable to read the file revision.');
        }

        return FileRevision::fromContents($contents);
    }

    private function writeUnderLock(
        FilesystemRoot $root,
        RelativePath $relativePath,
        string $contents,
        ?FileRevision $expectedRevision,
    ): FileRevision {
        $targetPath = $this->pathResolver->resolve($root, $relativePath);
        $actualRevision = $this->revision($root, $relativePath);

        if ($expectedRevision !== null && !$expectedRevision->equals($actualRevision)) {
            throw new RevisionConflictException($expectedRevision, $actualRevision);
        }

        $parentDirectory = dirname($targetPath);
        if (!is_dir($parentDirectory) && !mkdir($parentDirectory, 0o750, true) && !is_dir($parentDirectory)) {
            throw new FilesystemException('Unable to create the destination directory.');
        }

        // Resolve again after directory creation to detect any symlinked parent.
        $targetPath = $this->pathResolver->resolve($root, $relativePath);
        $temporaryPath = tempnam($parentDirectory, '.cms-write-');
        if ($temporaryPath === false) {
            throw new FilesystemException('Unable to create a temporary file for atomic write.');
        }

        try {
            $this->writeTemporaryFile($temporaryPath, $contents);
            if (!chmod($temporaryPath, 0o640)) {
                throw new FilesystemException('Unable to secure temporary file permissions.');
            }

            if (!rename($temporaryPath, $targetPath)) {
                throw new FilesystemException('Unable to atomically replace the destination file.');
            }
        } finally {
            if (file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return FileRevision::fromContents($contents);
    }

    private function writeTemporaryFile(string $temporaryPath, string $contents): void
    {
        $handle = fopen($temporaryPath, 'wb');
        if ($handle === false) {
            throw new FilesystemException('Unable to open a temporary file for writing.');
        }

        try {
            $remaining = $contents;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw new FilesystemException('Unable to write complete file contents.');
                }

                $remaining = substr($remaining, $written);
            }

            if (!fflush($handle) || !fsync($handle)) {
                throw new FilesystemException('Unable to flush the temporary file to disk.');
            }
        } finally {
            fclose($handle);
        }
    }
}
