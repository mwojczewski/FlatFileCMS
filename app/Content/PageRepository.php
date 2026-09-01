<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

use FilesystemIterator;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class PageRepository
{
    public function __construct(
        private YamlFileRepository $yaml,
        private SafePathResolver $paths,
        private ?ContentFileIndex $index = null,
    ) {}

    /** @return list<Page> */
    public function all(LanguageConfig $languages): array
    {
        if ($this->index !== null) {
            $identities = array_map(PageIdentity::fromString(...), $this->index->pages());
        } else {
            $root = $this->paths->rootPath(FilesystemRoot::Pages);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            );
            $identities = [];

            /** @var SplFileInfo $item */
            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->getFilename() !== 'content.yml') {
                    continue;
                }

                $directory = \dirname($item->getPathname());
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($directory, \strlen($root) + 1));
                $identities[] = PageIdentity::fromString($relative);
            }

            usort(
                $identities,
                static fn(PageIdentity $left, PageIdentity $right): int => $left->value() <=> $right->value(),
            );
        }

        return array_map(
            fn(PageIdentity $identity): Page => $this->get($identity, $languages),
            $identities,
        );
    }

    public function get(PageIdentity $identity, LanguageConfig $languages): Page
    {
        $relativePath = RelativePath::fromString($identity->value() . '/content.yml');

        try {
            $document = $this->yaml->read(FilesystemRoot::Pages, $relativePath);
            $absolutePath = $this->paths->resolve(FilesystemRoot::Pages, $relativePath, mustExist: true);
            clearstatcache(true, $absolutePath);
            $modifiedAt = filemtime($absolutePath);
            if ($modifiedAt === false) {
                throw new InvalidContentException('Unable to read page modification time.');
            }

            return $this->fromData($identity, $document->data(), $languages, $document->revision(), $modifiedAt);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidContentException(
                \sprintf('Page "%s" contains invalid content.', $identity->value()),
                previous: $exception,
            );
        }
    }

    /** @param array<string, mixed> $data */
    public function fromData(
        PageIdentity $identity,
        array $data,
        LanguageConfig $languages,
        FileRevision $revision,
        int $modifiedAt,
    ): Page {
        try {
            if (ContentData::integer($data['schemaVersion'] ?? null, 'schemaVersion') !== 1) {
                throw new InvalidArgumentException('Unsupported page schema version.');
            }

            $enabled = ContentData::boolean($data['enabled'] ?? null, 'enabled');
            $layout = isset($data['layout']) ? ContentData::string($data['layout'], 'layout') : null;
            if ($layout !== null && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $layout) !== 1) {
                throw new InvalidArgumentException('Layout name is invalid.');
            }

            $slugs = $identity->isHomepage() ? [] : $this->slugs($data['slug'] ?? null, $languages);
            $titles = $this->localizedStrings($data['title'] ?? null, 'title', $languages);
            $seo = isset($data['seo']) ? ContentData::map($data['seo'], 'seo') : [];
            $blocks = $this->blocks($data['blocks'] ?? []);
            $attributes = array_diff_key($data, array_flip([
                'schemaVersion',
                'enabled',
                'layout',
                'slug',
                'title',
                'seo',
                'blocks',
            ]));

            return new Page(
                $identity,
                $enabled,
                $layout,
                $slugs,
                $titles,
                $seo,
                $blocks,
                $attributes,
                $revision,
                $modifiedAt,
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidContentException(
                \sprintf('Page "%s" contains invalid content.', $identity->value()),
                previous: $exception,
            );
        }
    }

    /** @return array<string, Slug> */
    private function slugs(mixed $value, LanguageConfig $languages): array
    {
        $localized = $this->localizedStrings($value, 'slug', $languages);
        $slugs = [];
        foreach ($localized as $locale => $slug) {
            $slugs[$locale] = Slug::fromString($slug);
        }

        return $slugs;
    }

    /** @return array<string, string> */
    private function localizedStrings(mixed $value, string $field, LanguageConfig $languages): array
    {
        $mapping = ContentData::map($value, $field);
        $fallback = ContentData::string(
            $mapping[$languages->default()] ?? null,
            $field . '.' . $languages->default(),
        );
        $localized = [];

        foreach ($languages->codes() as $locale) {
            $localized[$locale] = ContentData::string($mapping[$locale] ?? $fallback, $field . '.' . $locale);
        }

        return $localized;
    }

    /** @return list<array<string, mixed>> */
    private function blocks(mixed $value): array
    {
        $blocks = [];
        foreach (ContentData::list($value, 'blocks') as $index => $item) {
            $blocks[] = ContentData::map($item, "blocks.{$index}");
        }

        return $blocks;
    }
}
