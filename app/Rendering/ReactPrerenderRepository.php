<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use RuntimeException;

final readonly class ReactPrerenderRepository
{
    private const int MAX_HTML_BYTES = 16 * 1024 * 1024;

    public function __construct(private string $projectRoot) {}

    public function render(string $locale, string $contentPath): RenderedPage
    {
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D', $locale) !== 1) {
            throw new RuntimeException('Invalid prerender locale.');
        }

        $segments = $contentPath === '' ? [] : explode('/', $contentPath);
        foreach ($segments as $segment) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $segment) !== 1) {
                throw new RuntimeException('Invalid prerender path.');
            }
        }

        $root = $this->projectRoot . '/public/app/prerender';
        $path = $root . '/' . $locale;
        if ($segments !== []) {
            $path .= '/' . implode('/', $segments);
        }
        $path .= '/index.html';

        $this->assertNoSymlink($root, $locale, $segments);
        clearstatcache(true, $path);
        if (!is_file($path) || is_link($path)) {
            throw new PrerenderNotFoundException($locale, $contentPath);
        }

        $size = filesize($path);
        $modifiedAt = filemtime($path);
        if ($size === false || $size > self::MAX_HTML_BYTES || $modifiedAt === false) {
            throw new RuntimeException('Unable to read a safe React prerender.');
        }
        $html = file_get_contents($path);
        if ($html === false) {
            throw new RuntimeException('Unable to read React prerender HTML.');
        }

        return new RenderedPage($html, $modifiedAt);
    }

    /** @param list<string> $segments */
    private function assertNoSymlink(string $root, string $locale, array $segments): void
    {
        $path = $root;
        foreach ([$locale, ...$segments] as $segment) {
            $path .= '/' . $segment;
            if (is_link($path)) {
                throw new RuntimeException('React prerender path cannot contain symlinks.');
            }
        }
    }
}
