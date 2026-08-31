<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use finfo;

final readonly class MediaInspector
{
    public function __construct(private SvgSanitizer $svg) {}

    /** @return array{contents: string, mimeType: string, width: ?int, height: ?int} */
    public function inspect(string $contents, ?string $clientFilename = null): array
    {
        if ($contents === '') {
            throw new MediaException('Media file cannot be empty.');
        }

        $extension = $clientFilename === null ? '' : strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            $contents = $this->svg->sanitize($contents);

            return ['contents' => $contents, 'mimeType' => 'image/svg+xml', 'width' => null, 'height' => null];
        }

        $detector = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $detector->buffer($contents);
        if (!\is_string($mimeType) || $mimeType === '') {
            throw new MediaException('Media MIME type could not be detected.');
        }

        $width = null;
        $height = null;
        if (MediaTypes::isImage($mimeType)) {
            $dimensions = @getimagesizefromstring($contents);
            if ($dimensions === false) {
                throw new MediaException('Image structure could not be verified.');
            }
            $width = $dimensions[0];
            $height = $dimensions[1];
        }

        return ['contents' => $contents, 'mimeType' => $mimeType, 'width' => $width, 'height' => $height];
    }
}
