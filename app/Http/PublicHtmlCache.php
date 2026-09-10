<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;

final readonly class PublicHtmlCache
{
    private string $directory;
    private string $generationFile;

    public function __construct(
        SafePathResolver $paths,
        private bool $enabled,
        private string $release,
    ) {
        $this->directory = $paths->resolve(FilesystemRoot::Storage, RelativePath::fromString('cache/html'));
        $this->generationFile = $paths->resolve(
            FilesystemRoot::Storage,
            RelativePath::fromString('cache/html-generation'),
        );
    }

    public function invalidate(): void
    {
        if (!$this->enabled) {
            return;
        }
        $directory = \dirname($this->generationFile);
        if (is_link($this->generationFile) || is_link($directory)) {
            return;
        }
        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            return;
        }
        $handle = fopen($this->generationFile, 'c+');
        if ($handle === false) {
            return;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, bin2hex(random_bytes(16)));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string, mixed> $query */
    public function get(Request $request, string $locale, string $path, array $query): ?PublicHtmlCacheEntry
    {
        if (!$this->eligible($request)) {
            return null;
        }

        $file = $this->file($locale, $path, $query);
        if (!is_file($file) || is_link($file)) {
            return null;
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        $data = @unserialize($contents, ['allowed_classes' => false]);
        if (
            !\is_array($data)
            || !\is_string($data['html'] ?? null)
            || !\is_int($data['modifiedAt'] ?? null)
            || !\is_string($data['contentHash'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $data['contentHash']) !== 1
            || !hash_equals($data['contentHash'], hash('sha256', $data['html']))
        ) {
            return null;
        }

        return new PublicHtmlCacheEntry($data['html'], $data['modifiedAt'], $data['contentHash']);
    }

    /** @param array<string, mixed> $query */
    public function put(Request $request, string $locale, string $path, array $query, string $html, int $modifiedAt): void
    {
        if (!$this->eligible($request)) {
            return;
        }
        if (is_link($this->directory)) {
            return;
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            return;
        }

        $contents = serialize([
            'html' => $html,
            'modifiedAt' => $modifiedAt,
            'contentHash' => hash('sha256', $html),
        ]);

        $file = $this->file($locale, $path, $query);
        $temporary = tempnam($this->directory, '.cms-html-');
        if ($temporary === false) {
            return;
        }
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0o600)) {
                return;
            }
            rename($temporary, $file);
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function eligible(Request $request): bool
    {
        return $this->enabled && \in_array($request->method(), ['GET', 'HEAD'], true);
    }

    /** @param array<string, mixed> $query */
    private function file(string $locale, string $path, array $query): string
    {
        $key = json_encode(
            [$this->release, $this->generation(), $locale, trim($path, '/'), $this->canonicalQuery($query)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->directory . '/' . hash('sha256', (string) $key) . '.cache';
    }

    private function generation(): string
    {
        $contents = is_file($this->generationFile) && !is_link($this->generationFile)
            ? file_get_contents($this->generationFile)
            : false;
        $generation = \is_string($contents) ? trim($contents) : '';
        if (preg_match('/^[a-f0-9]{32}$/D', $generation) === 1) {
            return $generation;
        }

        $this->invalidate();
        $contents = is_file($this->generationFile) && !is_link($this->generationFile)
            ? file_get_contents($this->generationFile)
            : false;
        $generation = \is_string($contents) ? trim($contents) : '';

        return preg_match('/^[a-f0-9]{32}$/D', $generation) === 1 ? $generation : 'unavailable';
    }

    /** @param array<string, mixed> $query */
    private function canonicalQuery(array $query): string
    {
        $pairs = [];
        $this->flattenQuery($query, '', $pairs);
        sort($pairs);

        return implode('&', $pairs);
    }

    /** @param list<string> $pairs */
    private function flattenQuery(mixed $value, string $prefix, array &$pairs): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
                $this->flattenQuery($item, $name, $pairs);
            }

            return;
        }

        $pairs[] = rawurlencode($prefix) . '=' . rawurlencode(\is_scalar($value) ? (string) $value : '');
    }
}
