<?php

declare(strict_types=1);

namespace FlatFileCms\Analytics;

use JsonException;

final readonly class AnalyticsCache
{
    private string $directory;

    public function __construct(string $projectRoot)
    {
        $this->directory = $projectRoot . '/storage/cache/analytics';
    }

    /** @return array<string, mixed>|null */
    public function read(string $key, int $maximumAge): ?array
    {
        $path = $this->path($key);
        $modified = is_file($path) ? filemtime($path) : false;
        if ($modified === false || $modified < time() - $maximumAge) {
            return null;
        }

        return $this->decode($path);
    }

    /** @return array<string, mixed>|null */
    public function readStale(string $key): ?array
    {
        return $this->decode($this->path($key));
    }

    /** @param array<string, mixed> $data */
    public function write(string $key, array $data): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            return;
        }
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return;
        }
        $path = $this->path($key);
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            return;
        }
        chmod($temporary, 0o600);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
        }
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }

    /** @return array<string, mixed>|null */
    private function decode(string $path): ?array
    {
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if (!\is_string($contents)) {
            return null;
        }
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!\is_array($decoded)) {
            return null;
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
