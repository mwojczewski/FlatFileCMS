<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use RuntimeException;

final readonly class UploadedFile
{
    public function __construct(
        private string $temporaryPath,
        private string $clientFilename,
        private int $size,
        private int $error = UPLOAD_ERR_OK,
        private bool $httpUpload = false,
    ) {}

    public static function fromHttpUpload(string $temporaryPath, string $clientFilename, int $size, int $error): self
    {
        return new self($temporaryPath, $clientFilename, $size, $error, true);
    }

    public function clientFilename(): string
    {
        return $this->clientFilename;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function error(): int
    {
        return $this->error;
    }

    public function contents(int $maximumBytes): string
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Uploaded file is incomplete.');
        }
        if ($this->size < 1 || $this->size > $maximumBytes) {
            throw new RuntimeException('Uploaded file size is outside the allowed range.');
        }
        if (!is_file($this->temporaryPath) || is_link($this->temporaryPath)) {
            throw new RuntimeException('Uploaded file is unavailable.');
        }
        if ($this->httpUpload && !is_uploaded_file($this->temporaryPath)) {
            throw new RuntimeException('File was not received through HTTP upload.');
        }

        $actualSize = filesize($this->temporaryPath);
        if ($actualSize === false || $actualSize !== $this->size || $actualSize > $maximumBytes) {
            throw new RuntimeException('Uploaded file size could not be verified.');
        }
        $contents = file_get_contents($this->temporaryPath);
        if ($contents === false || \strlen($contents) !== $actualSize) {
            throw new RuntimeException('Uploaded file could not be read.');
        }

        return $contents;
    }
}
