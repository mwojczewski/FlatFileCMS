<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class TemporaryProject
{
    private function __construct(private string $path) {}

    public static function create(): self
    {
        $path = sys_get_temp_dir() . '/flatfile-cms-' . bin2hex(random_bytes(8));
        foreach ([
            'blocks',
            'pages',
            'config',
            'public/assets/css',
            'storage/tmp',
            'storage/cache',
            'storage/database',
            'storage/sessions',
            'templates/layouts',
            'templates/partials',
        ] as $directory) {
            $absolutePath = $path . '/' . $directory;
            if (!mkdir($absolutePath, 0o700, true) && !is_dir($absolutePath)) {
                throw new RuntimeException('Unable to create temporary project directory.');
            }
        }

        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            throw new RuntimeException('Unable to resolve temporary project directory.');
        }

        return new self($resolvedPath);
    }

    public function path(string $relativePath = ''): string
    {
        return $relativePath === '' ? $this->path : $this->path . '/' . ltrim($relativePath, '/');
    }

    public function write(string $relativePath, string $contents): void
    {
        $path = $this->path($relativePath);
        $directory = \dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create temporary file parent.');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write temporary project file.');
        }
    }

    public function remove(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }

        rmdir($this->path);
    }
}
