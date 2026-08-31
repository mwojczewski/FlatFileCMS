<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Media\MediaException;
use FlatFileCms\Media\MediaFile;
use FlatFileCms\Media\MediaName;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaUrlGenerator;
use InvalidArgumentException;

final readonly class RenderContext
{
    public function __construct(
        private string $locale,
        private MarkdownRenderer $markdownRenderer,
        private PartialRenderer $partials,
        private ?PageIdentity $pageIdentity = null,
        private ?MediaRepository $media = null,
        private ?MediaUrlGenerator $mediaUrls = null,
    ) {}

    public function escape(string|int|float $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function markdown(string $text): string
    {
        return $this->markdownRenderer->render($text);
    }

    /**
     * @param array<string, mixed> $image
     * @param array<string, string> $attributes
     */
    public function image(
        array $image,
        ?int $width = null,
        ?int $height = null,
        ?string $format = null,
        string $fit = 'contain',
        array $attributes = [],
    ): string {
        $file = $this->mediaFile($image);
        $src = $this->variantUrl($file, $width, $height, $format, $fit);
        $alt = $this->imageAlt($image);
        [$htmlWidth, $htmlHeight] = $this->displayDimensions($file, $width, $height, $fit);

        return \sprintf(
            '<img src="%s" alt="%s"%s%s>',
            $this->escape($src),
            $this->escape($alt),
            $htmlWidth === null ? '' : \sprintf(
                ' width="%d" height="%d"',
                $htmlWidth,
                $htmlHeight,
            ),
            $this->imageAttributes($attributes),
        );
    }

    /** @param array<string, mixed> $image */
    public function imageUrl(
        array $image,
        ?int $width = null,
        ?int $height = null,
        ?string $format = null,
        string $fit = 'contain',
    ): string {
        return $this->escape($this->variantUrl($this->mediaFile($image), $width, $height, $format, $fit));
    }

    /**
     * @param array<string, mixed> $image
     * @param list<int> $widths
     * @param array<string, string> $attributes
     */
    public function picture(
        array $image,
        array $widths,
        string $format = 'webp',
        ?float $aspectRatio = null,
        string $fit = 'contain',
        string $sizes = '100vw',
        array $attributes = [],
    ): string {
        if ($widths === []) {
            throw new RenderingException('Responsive image widths cannot be empty.');
        }
        $normalizedWidths = [];
        foreach ($widths as $width) {
            if ($width < 1 || $width > 8192) {
                throw new RenderingException('Responsive image width is invalid.');
            }
            $normalizedWidths[] = $width;
        }
        $normalizedWidths = array_values(array_unique($normalizedWidths));
        sort($normalizedWidths);
        if ($fit === 'cover' && ($aspectRatio === null || $aspectRatio <= 0)) {
            throw new RenderingException('Cover picture variants require a positive aspect ratio.');
        }
        if ($sizes === '' || preg_match('/[\x00-\x1F\x7F"\'<>]/', $sizes) === 1) {
            throw new RenderingException('Responsive image sizes expression is invalid.');
        }

        $file = $this->mediaFile($image);
        $sources = [];
        foreach ($normalizedWidths as $width) {
            $height = $aspectRatio === null ? null : max(1, (int) round($width / $aspectRatio));
            $sources[] = $this->variantUrl($file, $width, $height, $format, $fit) . " {$width}w";
        }
        $fallbackWidth = $normalizedWidths[array_key_last($normalizedWidths)];
        $fallbackHeight = $aspectRatio === null ? null : max(1, (int) round($fallbackWidth / $aspectRatio));
        $fallback = $this->variantUrl($file, $fallbackWidth, $fallbackHeight, null, $fit);
        $alt = $this->imageAlt($image);
        [$htmlWidth, $htmlHeight] = $this->displayDimensions($file, $fallbackWidth, $fallbackHeight, $fit);

        return \sprintf(
            '<picture><source type="%s" srcset="%s" sizes="%s"><img src="%s" alt="%s"%s%s></picture>',
            $this->escape($this->formatMimeType($format)),
            $this->escape(implode(', ', $sources)),
            $this->escape($sizes),
            $this->escape($fallback),
            $this->escape($alt),
            $htmlWidth === null ? '' : \sprintf(' width="%d" height="%d"', $htmlWidth, $htmlHeight),
            $this->imageAttributes($attributes),
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

    /** @param array<string, mixed> $image */
    private function mediaFile(array $image): MediaFile
    {
        $src = $image['src'] ?? null;
        if (!\is_string($src) || $src === '') {
            throw new RenderingException('Normalized image data requires src.');
        }

        try {
            if ($this->pageIdentity === null || $this->media === null || $this->mediaUrls === null) {
                throw new MediaException('Media context is unavailable.');
            }

            return $this->media->get($this->pageIdentity, MediaName::fromString($src));
        } catch (InvalidArgumentException|MediaException $exception) {
            throw new RenderingException('Normalized image path is invalid.', previous: $exception);
        }
    }

    private function variantUrl(
        MediaFile $file,
        ?int $width,
        ?int $height,
        ?string $format,
        string $fit,
    ): string {
        if ($this->pageIdentity === null || $this->mediaUrls === null) {
            throw new RenderingException('Media context is unavailable.');
        }
        if (($width !== null && ($width < 1 || $width > 8192)) || ($height !== null && ($height < 1 || $height > 8192))) {
            throw new RenderingException('Image dimensions are invalid.');
        }
        if ($format !== null && !\in_array($format, ['avif', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RenderingException('Image output format is invalid.');
        }
        if (!\in_array($fit, ['contain', 'cover'], true) || ($fit === 'cover' && ($width === null || $height === null))) {
            throw new RenderingException('Image fit configuration is invalid.');
        }

        return $this->mediaUrls->variant($this->pageIdentity, $file->item(), $width, $height, $format, $fit);
    }

    /** @param array<string, mixed> $image */
    private function imageAlt(array $image): string
    {
        $alt = $image['alt'] ?? '';
        if (!\is_string($alt)) {
            throw new RenderingException('Normalized image alt must be a string.');
        }

        return $alt;
    }

    /** @return array{?int, ?int} */
    private function displayDimensions(
        MediaFile $file,
        ?int $width,
        ?int $height,
        string $fit,
    ): array {
        if ($fit === 'cover' && $width !== null && $height !== null) {
            return [$width, $height];
        }

        return [$file->item()->width(), $file->item()->height()];
    }

    /** @param array<string, string> $attributes */
    private function imageAttributes(array $attributes): string
    {
        $defaults = ['loading' => 'lazy', 'decoding' => 'async'];
        $attributes = [...$defaults, ...$attributes];
        $allowed = ['class', 'decoding', 'fetchpriority', 'id', 'loading'];
        $html = '';
        foreach ($attributes as $name => $value) {
            if (!\in_array($name, $allowed, true) || preg_match('/^[A-Za-z0-9 _-]*$/D', $value) !== 1) {
                throw new RenderingException('Image HTML attribute is invalid.');
            }
            $html .= ' ' . $name . '="' . $this->escape($value) . '"';
        }

        return $html;
    }

    private function formatMimeType(string $format): string
    {
        return match ($format) {
            'avif' => 'image/avif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new RenderingException('Image output format is invalid.'),
        };
    }
}
