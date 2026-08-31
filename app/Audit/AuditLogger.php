<?php

declare(strict_types=1);

namespace FlatFileCms\Audit;

use DateTimeImmutable;
use DateTimeZone;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use JsonException;

final readonly class AuditLogger
{
    private string $directory;

    public function __construct(SafePathResolver $paths)
    {
        $directory = $paths->rootPath(FilesystemRoot::Storage) . DIRECTORY_SEPARATOR . 'audit';
        if (is_link($directory)) {
            throw new FilesystemException('Audit directory cannot be a symbolic link.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0o700) && !is_dir($directory)) {
            throw new FilesystemException('Unable to create the audit directory.');
        }

        $resolved = realpath($directory);
        if ($resolved === false) {
            throw new FilesystemException('Unable to resolve the audit directory.');
        }
        $this->directory = $resolved;
    }

    /** @param array<string, mixed> $metadata */
    public function log(
        string $action,
        ?int $userId,
        string $resource,
        string $ip,
        array $metadata = [],
    ): void {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $entry = [
            'timestamp' => $now->format(DATE_ATOM),
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'ip' => $ip,
            'metadata' => $metadata,
        ];

        try {
            $line = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (JsonException $exception) {
            throw new FilesystemException('Unable to encode the audit entry.', previous: $exception);
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $now->format('Y-m-d') . '.jsonl';
        $newFile = !file_exists($path);
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new FilesystemException('Unable to open the audit log.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new FilesystemException('Unable to lock the audit log.');
            }
            if (fwrite($handle, $line) !== \strlen($line) || !fflush($handle)) {
                throw new FilesystemException('Unable to append the audit entry.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if ($newFile && !chmod($path, 0o600)) {
            throw new FilesystemException('Unable to secure the audit log permissions.');
        }
    }
}
