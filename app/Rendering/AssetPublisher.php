<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

final class AssetPublisher
{
    private const int MAX_ASSET_BYTES = 1_048_576;

    private string $publicRoot;

    public function __construct(string $projectRoot)
    {
        $publicRoot = realpath(rtrim($projectRoot, '/\\') . '/public');
        if ($publicRoot === false || !is_dir($publicRoot)) {
            throw new RenderingException('Public directory is unavailable.');
        }

        $this->publicRoot = $publicRoot;
    }

    public function publish(string $type, string $source): string
    {
        if (!is_file($source) || is_link($source)) {
            throw new RenderingException('Block asset is not a regular file.');
        }

        $size = filesize($source);
        if ($size === false || $size > self::MAX_ASSET_BYTES) {
            throw new RenderingException('Block asset exceeds the size limit.');
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new RenderingException('Block asset cannot be read.');
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        if (!\in_array($extension, ['css', 'js'], true)) {
            throw new RenderingException('Block asset extension is not supported.');
        }

        $directory = "{$this->publicRoot}/assets/blocks/{$type}";
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new RenderingException('Block asset directory cannot be created.');
        }

        $filename = "{$type}." . substr(hash('sha256', $contents), 0, 16) . ".{$extension}";
        $target = "{$directory}/{$filename}";
        if (!is_file($target)) {
            $temporary = "{$directory}/.{$filename}." . bin2hex(random_bytes(8)) . '.tmp';
            try {
                if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0o644)) {
                    throw new RenderingException('Block asset cannot be written.');
                }
                if (!rename($temporary, $target)) {
                    throw new RenderingException('Block asset cannot be published atomically.');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }

        return "/assets/blocks/{$type}/{$filename}";
    }
}
