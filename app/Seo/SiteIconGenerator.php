<?php

declare(strict_types=1);

namespace FlatFileCms\Seo;

use FlatFileCms\Http\UploadedFile;
use FlatFileCms\Media\RasterImageProcessor;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final readonly class SiteIconGenerator
{
    private string $publicRoot;

    public function __construct(string $projectRoot, private RasterImageProcessor $images)
    {
        $this->publicRoot = rtrim($projectRoot, '/\\') . '/public';
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, string>
     */
    public function generate(?UploadedFile $upload, array $manifest): array
    {
        $directory = $this->publicRoot . '/assets/icons';
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie udało się utworzyć katalogu ikon.');
        }
        if (is_link($directory)) {
            throw new RuntimeException('Katalog ikon nie może być dowiązaniem symbolicznym.');
        }

        if ($upload !== null) {
            $contents = $upload->contents(10_485_760);
            $dimensions = @getimagesizefromstring($contents);
            if ($dimensions === false || !\in_array($dimensions['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw new InvalidArgumentException('Źródło ikon musi być obrazem PNG, JPEG lub WebP.');
            }
            if ($dimensions[0] !== $dimensions[1] || $dimensions[0] < 512) {
                throw new InvalidArgumentException('Źródło ikon musi być kwadratowe i mieć co najmniej 512×512 px.');
            }
            foreach ([16, 32, 180, 192, 512] as $size) {
                $icon = $this->images->transform($contents, $dimensions['mime'], $size, $size, 'png', 90, 40_000_000, 'cover');
                $this->write("{$directory}/icon-{$size}x{$size}.png", $icon['contents']);
            }
            $png32 = file_get_contents("{$directory}/icon-32x32.png");
            if (!\is_string($png32)) {
                throw new RuntimeException('Nie udało się przygotować favicon.ico.');
            }
            $this->write($this->publicRoot . '/favicon.ico', $this->ico($png32, 32));
            $apple = file_get_contents("{$directory}/icon-180x180.png");
            if (!\is_string($apple)) {
                throw new RuntimeException('Nie udało się przygotować ikon Apple.');
            }
            $this->write($this->publicRoot . '/apple-touch-icon.png', $apple);
            $this->write($this->publicRoot . '/apple-touch-icon-precomposed.png', $apple);
        }

        $this->writeManifest($manifest);

        $paths = ['manifest' => '/site.webmanifest'];
        if ($upload !== null || is_file($this->publicRoot . '/assets/icons/icon-512x512.png')) {
            $paths = [...$paths, ...[
                'ico' => '/favicon.ico',
                'png32' => '/assets/icons/icon-32x32.png',
                'png16' => '/assets/icons/icon-16x16.png',
                'appleTouch' => '/apple-touch-icon.png',
                'appleTouchPrecomposed' => '/apple-touch-icon-precomposed.png',
            ]];
        }

        return $paths;
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        $path = $this->publicRoot . '/site.webmanifest';
        if (!is_file($path) || is_link($path)) {
            return [];
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!\is_array($decoded) || array_is_list($decoded)) {
            return [];
        }
        $manifest = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $manifest[$key] = $value;
            }
        }

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(array $manifest): void
    {
        $document = [
            'name' => $this->text($manifest['name'] ?? null, 'Nazwa aplikacji', 120),
            'short_name' => $this->text($manifest['short_name'] ?? null, 'Krótka nazwa', 32),
            'description' => $this->optionalText($manifest['description'] ?? null, 240),
            'start_url' => $this->path($manifest['start_url'] ?? '/'),
            'scope' => $this->path($manifest['scope'] ?? '/'),
            'display' => \in_array($manifest['display'] ?? null, ['browser', 'minimal-ui', 'standalone', 'fullscreen'], true) ? $manifest['display'] : 'standalone',
            'theme_color' => $this->color($manifest['theme_color'] ?? '#168761'),
            'background_color' => $this->color($manifest['background_color'] ?? '#ffffff'),
            'icons' => [
                ['src' => '/assets/icons/icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/assets/icons/icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ];
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->write($this->publicRoot . '/site.webmanifest', $json . "\n");
    }

    private function text(mixed $value, string $label, int $maximum): string
    {
        if (!\is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maximum) {
            throw new InvalidArgumentException("{$label} jest wymagane i może mieć maksymalnie {$maximum} znaków.");
        }

        return trim($value);
    }

    private function optionalText(mixed $value, int $maximum): string
    {
        if (!\is_string($value) || mb_strlen(trim($value)) > $maximum) {
            throw new InvalidArgumentException('Opis manifestu jest nieprawidłowy.');
        }

        return trim($value);
    }

    private function path(mixed $value): string
    {
        if (!\is_string($value) || !str_starts_with($value, '/') || str_contains($value, "\n")) {
            throw new InvalidArgumentException('Adres manifestu musi być ścieżką rozpoczynającą się od /.');
        }

        return $value;
    }

    private function color(mixed $value): string
    {
        if (!\is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Kolor manifestu musi mieć format #RRGGBB.');
        }

        return strtolower($value);
    }

    private function ico(string $png, int $size): string
    {
        return pack('vvv', 0, 1, 1) . pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, \strlen($png), 22) . $png;
    }

    private function write(string $path, string $contents): void
    {
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Nie udało się bezpiecznie zapisać wygenerowanego pliku.');
        }
    }
}
