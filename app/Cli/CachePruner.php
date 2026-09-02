<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use FilesystemIterator;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class CachePruner
{
    private string $projectRoot;
    private string $storageCacheRoot;

    public function __construct(SafePathResolver $paths)
    {
        $this->projectRoot = \dirname($paths->rootPath(FilesystemRoot::Storage));
        $this->storageCacheRoot = $paths->rootPath(FilesystemRoot::Storage) . DIRECTORY_SEPARATOR . 'cache';
    }

    public function prune(bool $dryRun, int $assetMaxAge, int $cacheMaxAge, ?int $now = null): PruneResult
    {
        if ($assetMaxAge < 1 || $cacheMaxAge < 1) {
            throw new \InvalidArgumentException('Prune retention must be at least one second.');
        }

        $now ??= time();
        $assets = $this->pruneBlockAssets($dryRun, $now - $assetMaxAge);
        $cache = $this->pruneStorageCache($dryRun, $now - $cacheMaxAge);

        return $assets->plus($cache);
    }

    private function pruneBlockAssets(bool $dryRun, int $cutoff): PruneResult
    {
        $root = $this->projectRoot . '/public/assets/blocks';
        if (!is_dir($root) || is_link($root)) {
            return new PruneResult(0, 0);
        }
        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || !str_starts_with($resolvedRoot, $this->projectRoot . DIRECTORY_SEPARATOR)) {
            throw new FilesystemException('Block asset root escapes the project directory.');
        }

        $current = $this->currentBlockAssets();

        return $this->pruneFiles(
            $resolvedRoot,
            $cutoff,
            $dryRun,
            static function (SplFileInfo $file) use ($current): bool {
                $name = $file->getFilename();
                if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.[a-f0-9]{16}\.(?:css|js)$/D', $name) !== 1) {
                    return false;
                }

                return !isset($current[$name]);
            },
        );
    }

    /** @return array<string, true> */
    private function currentBlockAssets(): array
    {
        $current = [];
        $blocksRoot = $this->projectRoot . '/blocks';
        $entries = is_dir($blocksRoot) && !is_link($blocksRoot) ? scandir($blocksRoot) : [];
        if ($entries === false) {
            throw new FilesystemException('Unable to enumerate block definitions.');
        }
        foreach ($entries as $type) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $type) !== 1) {
                continue;
            }
            foreach (['style.css' => 'css', 'script.js' => 'js'] as $sourceName => $extension) {
                $source = "{$blocksRoot}/{$type}/{$sourceName}";
                if (!is_file($source) || is_link($source)) {
                    continue;
                }
                $contents = file_get_contents($source);
                if ($contents === false) {
                    throw new FilesystemException('Unable to read a current block asset.');
                }
                $current["{$type}." . substr(hash('sha256', $contents), 0, 16) . ".{$extension}"] = true;
            }
        }

        return $current;
    }

    private function pruneStorageCache(bool $dryRun, int $cutoff): PruneResult
    {
        if (!is_dir($this->storageCacheRoot) || is_link($this->storageCacheRoot)) {
            return new PruneResult(0, 0);
        }

        return $this->pruneFiles(
            $this->storageCacheRoot,
            $cutoff,
            $dryRun,
            static function (SplFileInfo $file): bool {
                $path = str_replace('\\', '/', $file->getPathname());

                return preg_match('#/yaml/[a-f0-9]{64}\.(?:json|serialized)$#D', $path) === 1
                    || preg_match('#/media/[a-f0-9]{2}/[a-f0-9]{64}/[a-f0-9]{64}\.(?:avif|jpe?g|png|webp)$#D', $path) === 1;
            },
        );
    }

    /** @param callable(SplFileInfo): bool $eligible */
    private function pruneFiles(string $root, int $cutoff, bool $dryRun, callable $eligible): PruneResult
    {
        $files = 0;
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isFile() || $item->getMTime() >= $cutoff || !$eligible($item)) {
                continue;
            }
            $size = $item->getSize();
            if (!$dryRun && !unlink($item->getPathname())) {
                throw new FilesystemException('Unable to remove an expired cache file.');
            }
            ++$files;
            $bytes += $size;
        }

        return new PruneResult($files, $bytes);
    }
}
