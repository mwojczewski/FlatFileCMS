<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use GdImage;

final class RasterImageProcessor
{
    public function stripMetadata(string $contents, string $mimeType, int $quality): string
    {
        if (!\in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            return $contents;
        }

        $image = $this->decode($contents);

        return $this->encode($image, $mimeType === 'image/jpeg' ? 'jpg' : 'png', $quality);
    }

    /** @return array{contents: string, mimeType: string, extension: string, width: int, height: int} */
    public function transform(
        string $contents,
        string $mimeType,
        ?int $requestedWidth,
        ?int $requestedHeight,
        string $format,
        int $quality,
        int $maximumPixels,
        string $fit = 'contain',
    ): array {
        if (!MediaTypes::isTransformable($mimeType)) {
            throw new MediaException('This image type cannot be transformed.');
        }
        $dimensions = @\getimagesizefromstring($contents);
        if ($dimensions === false) {
            throw new MediaException('Image dimensions could not be read.');
        }
        $sourceWidth = $dimensions[0];
        $sourceHeight = $dimensions[1];
        if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth > \intdiv($maximumPixels, $sourceHeight)) {
            throw new MediaException('Image pixel count exceeds the configured limit.');
        }

        [$width, $height] = $this->targetSize($sourceWidth, $sourceHeight, $requestedWidth, $requestedHeight, $fit);
        if ($width < 1 || $height < 1) {
            throw new MediaException('Calculated image dimensions are invalid.');
        }
        $source = $this->decode($contents);
        $target = \imagecreatetruecolor($width, $height);
        $this->prepareTransparency($target, $format);
        [$sourceX, $sourceY, $cropWidth, $cropHeight] = $this->crop(
            $sourceWidth,
            $sourceHeight,
            $width,
            $height,
            $fit,
        );
        \imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $width,
            $height,
            $cropWidth,
            $cropHeight,
        );
        $output = $this->encode($target, $format, $quality);
        $outputMime = match ($format) {
            'avif' => 'image/avif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new MediaException('Requested output format is invalid.'),
        };

        return ['contents' => $output, 'mimeType' => $outputMime, 'extension' => $format === 'jpeg' ? 'jpg' : $format, 'width' => $width, 'height' => $height];
    }

    private function decode(string $contents): GdImage
    {
        $image = @\imagecreatefromstring($contents);
        if (!$image instanceof GdImage) {
            throw new MediaException('Image decoder rejected the file.');
        }

        return $image;
    }

    private function encode(GdImage $image, string $format, int $quality): string
    {
        \ob_start();
        try {
            $success = match ($format) {
                'avif' => \function_exists('imageavif') && \imageavif($image, null, $quality),
                'jpg', 'jpeg' => \imagejpeg($image, null, $quality),
                'png' => \imagepng($image, null, (int) \round((100 - $quality) * 9 / 100)),
                'webp' => \function_exists('imagewebp') && \imagewebp($image, null, $quality),
                default => false,
            };
            $output = \ob_get_contents();
        } finally {
            \ob_end_clean();
        }
        if (!$success || !\is_string($output) || $output === '') {
            throw new MediaException('Requested image encoder is unavailable.');
        }

        return $output;
    }

    /** @return array{int, int} */
    private function targetSize(
        int $sourceWidth,
        int $sourceHeight,
        ?int $width,
        ?int $height,
        string $fit,
    ): array
    {
        if (!\in_array($fit, ['contain', 'cover'], true)) {
            throw new MediaException('Requested image fit mode is invalid.');
        }
        if ($fit === 'cover') {
            if ($width === null || $height === null) {
                throw new MediaException('Cover image variants require both width and height.');
            }
            $scale = \min($sourceWidth / $width, $sourceHeight / $height, 1.0);

            return [\max(1, (int) \round($width * $scale)), \max(1, (int) \round($height * $scale))];
        }
        if ($width === null && $height === null) {
            return [$sourceWidth, $sourceHeight];
        }
        $widthRatio = $width === null ? 1.0 : $width / $sourceWidth;
        $heightRatio = $height === null ? 1.0 : $height / $sourceHeight;
        $ratio = \min($widthRatio, $heightRatio, 1.0);

        return [\max(1, (int) \round($sourceWidth * $ratio)), \max(1, (int) \round($sourceHeight * $ratio))];
    }

    /** @return array{int, int, int, int} */
    private function crop(
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
        string $fit,
    ): array {
        if ($fit !== 'cover') {
            return [0, 0, $sourceWidth, $sourceHeight];
        }

        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;
        if ($sourceRatio > $targetRatio) {
            $cropWidth = \max(1, (int) \round($sourceHeight * $targetRatio));

            return [(int) \floor(($sourceWidth - $cropWidth) / 2), 0, $cropWidth, $sourceHeight];
        }

        $cropHeight = \max(1, (int) \round($sourceWidth / $targetRatio));

        return [0, (int) \floor(($sourceHeight - $cropHeight) / 2), $sourceWidth, $cropHeight];
    }

    private function prepareTransparency(GdImage $image, string $format): void
    {
        if (!\in_array($format, ['avif', 'png', 'webp'], true)) {
            return;
        }
        \imagealphablending($image, false);
        \imagesavealpha($image, true);
        $transparent = \imagecolorallocatealpha($image, 0, 0, 0, 127);
        if ($transparent !== false) {
            \imagefill($image, 0, 0, $transparent);
        }
    }
}
