<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use InvalidArgumentException;

final readonly class PublicMediaController
{
    public function __construct(
        private MediaRepository $media,
        private MediaVariantService $variants,
    ) {}

    public function show(Request $request): Response
    {
        try {
            [$identity, $fingerprint, $name] = $this->target((string) $request->attribute('path'));
            $file = $this->media->get($identity, $name);
            if (!hash_equals($file->item()->fingerprint(), $fingerprint)) {
                throw new HttpException(404, 'MEDIA_NOT_FOUND', 'Media not found.');
            }
            $variant = $this->variants->create(
                $file,
                $this->integerQuery($request, 'w'),
                $this->integerQuery($request, 'h'),
                $this->formatQuery($request),
                $this->fitQuery($request),
            );
        } catch (HttpException $exception) {
            throw $exception;
        } catch (InvalidArgumentException|MediaException $exception) {
            throw new HttpException(404, 'MEDIA_NOT_FOUND', 'Media not found.', previous: $exception);
        }

        $etag = '"' . $variant->etag() . '"';
        $headers = [
            'Content-Type' => $variant->mimeType(),
            'Content-Length' => (string) \strlen($variant->contents()),
            'Content-Disposition' => 'inline; filename="' . $variant->filename() . '"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($variant->mimeType() === 'image/svg+xml') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'none'; sandbox";
        }
        if ($request->header('if-none-match') === $etag) {
            return new Response('', 304, $headers);
        }

        $range = $this->range($request->header('range'), \strlen($variant->contents()));
        if ($range !== null) {
            [$start, $end] = $range;
            $contents = substr($variant->contents(), $start, $end - $start + 1);
            $headers['Content-Length'] = (string) \strlen($contents);
            $headers['Content-Range'] = "bytes {$start}-{$end}/" . \strlen($variant->contents());

            return new Response($contents, 206, $headers);
        }

        return new Response($variant->contents(), headers: $headers);
    }

    /** @return array{PageIdentity, string, MediaName} */
    private function target(string $path): array
    {
        $segments = explode('/', trim($path, '/'));
        if (\count($segments) < 3) {
            throw new InvalidArgumentException('Media URL is incomplete.');
        }
        $filename = array_pop($segments);
        $fingerprint = array_pop($segments);
        if (preg_match('/^[a-f0-9]{16}$/D', $fingerprint) !== 1) {
            throw new InvalidArgumentException('Media fingerprint is invalid.');
        }

        return [PageIdentity::fromString(implode('/', $segments)), $fingerprint, MediaName::fromString($filename)];
    }

    private function integerQuery(Request $request, string $name): ?int
    {
        $value = $request->query()[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!\is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new MediaException("Image {$name} parameter is invalid.");
        }

        return (int) $value;
    }

    private function formatQuery(Request $request): ?string
    {
        $value = $request->query()['format'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!\is_string($value) || !\in_array($value, ['avif', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new MediaException('Image format parameter is invalid.');
        }

        return $value;
    }

    private function fitQuery(Request $request): string
    {
        $value = $request->query()['fit'] ?? 'contain';
        if (!\is_string($value) || !\in_array($value, ['contain', 'cover'], true)) {
            throw new MediaException('Image fit parameter is invalid.');
        }

        return $value;
    }

    /** @return array{int, int}|null */
    private function range(?string $header, int $length): ?array
    {
        if ($header === null) {
            return null;
        }
        if (preg_match('/^bytes=([0-9]+)-([0-9]*)$/D', $header, $matches) !== 1) {
            throw new HttpException(416, 'MEDIA_RANGE_INVALID', 'Requested media range is invalid.');
        }
        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $length - 1 : (int) $matches[2];
        if ($start >= $length || $end < $start || $end >= $length) {
            throw new HttpException(416, 'MEDIA_RANGE_INVALID', 'Requested media range is invalid.');
        }

        return [$start, $end];
    }
}
