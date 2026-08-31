<?php

declare(strict_types=1);

namespace FlatFileCms\Config;

use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use InvalidArgumentException;

final readonly class SiteTextRepository
{
    private const int MAX_BYTES = 262_144;

    public function __construct(
        private SafePathResolver $paths,
        private AtomicFileWriter $writer,
    ) {}

    public function llms(): SiteTextDocument
    {
        return $this->read('llms.txt');
    }

    public function security(): SiteTextDocument
    {
        return $this->read('security.txt');
    }

    public function updateLlms(string $contents, FileRevision $revision): SiteTextDocument
    {
        return $this->write('llms.txt', $contents, $revision);
    }

    public function updateSecurity(string $contents, FileRevision $revision): SiteTextDocument
    {
        return $this->write('security.txt', $contents, $revision);
    }

    private function read(string $filename): SiteTextDocument
    {
        $relative = RelativePath::fromString($filename);
        $path = $this->paths->resolve(FilesystemRoot::Config, $relative);
        if (!\file_exists($path)) {
            return new SiteTextDocument('', FileRevision::missing());
        }
        if (!\is_file($path) || \is_link($path)) {
            throw new FilesystemException("Config text file {$filename} is unsafe.");
        }
        $contents = \file_get_contents($path);
        if ($contents === false) {
            throw new FilesystemException("Unable to read {$filename}.");
        }

        return new SiteTextDocument($contents, FileRevision::fromContents($contents));
    }

    private function write(string $filename, string $contents, FileRevision $revision): SiteTextDocument
    {
        if (\strlen($contents) > self::MAX_BYTES || \str_contains($contents, "\0")) {
            throw new InvalidArgumentException("{$filename} is too large or contains a null byte.");
        }
        $normalized = \str_replace(["\r\n", "\r"], "\n", $contents);
        if ($normalized !== '' && !\str_ends_with($normalized, "\n")) {
            $normalized .= "\n";
        }
        $newRevision = $this->writer->write(
            FilesystemRoot::Config,
            RelativePath::fromString($filename),
            $normalized,
            $revision,
        );

        return new SiteTextDocument($normalized, $newRevision);
    }
}
