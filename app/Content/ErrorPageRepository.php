<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;

final readonly class ErrorPageRepository
{
    public function __construct(
        private YamlFileRepository $yaml,
        private SafePathResolver $paths,
        private PageRepository $pages,
    ) {}

    public function find(int $status, LanguageConfig $languages): ?Page
    {
        if ($status < 400 || $status > 599) {
            return null;
        }

        foreach ([(string) $status, (string) (intdiv($status, 100) * 100)] as $candidate) {
            $relativePath = RelativePath::fromString($candidate . '/content.yml');
            $absolutePath = $this->paths->resolve(FilesystemRoot::Errors, $relativePath);
            if (!is_file($absolutePath)) {
                continue;
            }

            $document = $this->yaml->read(FilesystemRoot::Errors, $relativePath);
            $data = $document->data();
            // Error pages are not routable, but Page's parser expects localized slugs.
            $data['slug'] = array_fill_keys($languages->codes(), 'error-' . $candidate);
            clearstatcache(true, $absolutePath);

            return $this->pages->fromData(
                PageIdentity::fromString($candidate),
                $data,
                $languages,
                $document->revision(),
                filemtime($absolutePath) ?: time(),
            );
        }

        return null;
    }
}
