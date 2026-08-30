<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use InvalidArgumentException;

final readonly class RenderContext
{
    public function __construct(
        private string $locale,
        private string $pageUrl,
        private MarkdownRenderer $markdownRenderer,
        private PartialRenderer $partials,
    ) {}

    public function escape(string|int|float $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function markdown(string $text): string
    {
        return $this->markdownRenderer->render($text);
    }

    /** @param array<string, mixed> $image */
    public function image(array $image): string
    {
        $src = $image['src'] ?? null;
        if (!\is_string($src) || $src === '') {
            throw new RenderingException('Normalized image data requires src.');
        }

        try {
            $src = RelativePath::fromString($src)->value();
        } catch (InvalidArgumentException $exception) {
            throw new RenderingException('Normalized image path is invalid.', previous: $exception);
        }

        $alt = $image['alt'] ?? '';
        if (!\is_string($alt)) {
            throw new RenderingException('Normalized image alt must be a string.');
        }

        $base = $this->pageUrl === '/' ? '' : rtrim($this->pageUrl, '/');

        return \sprintf(
            '<img src="%s/%s" alt="%s" loading="lazy" decoding="async">',
            $this->escape($base),
            $this->escape($src),
            $this->escape($alt),
        );
    }

    public function asset(string $url): string
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            throw new RenderingException('Asset URL must be root-relative.');
        }

        return $this->escape($url);
    }

    public function url(string $path): string
    {
        if (str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            return $this->escape($path);
        }

        if (filter_var($path, FILTER_VALIDATE_URL) !== false) {
            return $this->escape($path);
        }

        throw new RenderingException('URL is invalid.');
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /** @param array<string, mixed> $data */
    public function partial(string $name, array $data = []): string
    {
        return $this->partials->render($name, $data, $this);
    }
}
