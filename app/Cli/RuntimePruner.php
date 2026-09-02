<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;

final readonly class RuntimePruner
{
    private string $sessionsRoot;

    public function __construct(SafePathResolver $paths)
    {
        $this->sessionsRoot = $paths->rootPath(FilesystemRoot::Storage) . DIRECTORY_SEPARATOR . 'sessions';
    }

    public function prune(bool $dryRun, int $maxAge, ?int $now = null): PruneResult
    {
        if ($maxAge < 1) {
            throw new \InvalidArgumentException('Session retention must be at least one second.');
        }
        if (!is_dir($this->sessionsRoot) || is_link($this->sessionsRoot)) {
            return new PruneResult(0, 0);
        }

        $entries = scandir($this->sessionsRoot);
        if ($entries === false) {
            throw new FilesystemException('Unable to enumerate session files.');
        }
        $cutoff = ($now ?? time()) - $maxAge;
        $files = 0;
        $bytes = 0;
        foreach ($entries as $entry) {
            if (preg_match('/^sess_[A-Za-z0-9,-]+$/D', $entry) !== 1) {
                continue;
            }
            $path = $this->sessionsRoot . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }
            $modifiedAt = filemtime($path);
            $size = filesize($path);
            if ($modifiedAt === false || $size === false) {
                throw new FilesystemException('Unable to read session file metadata.');
            }
            if ($modifiedAt >= $cutoff) {
                continue;
            }
            if (!$dryRun && !unlink($path)) {
                throw new FilesystemException('Unable to remove an expired session file.');
            }
            ++$files;
            $bytes += $size;
        }

        return new PruneResult($files, $bytes);
    }
}
