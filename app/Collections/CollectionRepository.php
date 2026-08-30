<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

use FilesystemIterator;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class CollectionRepository
{
    public function __construct(
        private YamlFileRepository $yaml,
        private SafePathResolver $paths,
    ) {}

    /** @return list<Collection> */
    public function all(LanguageConfig $languages): array
    {
        $root = $this->paths->rootPath(FilesystemRoot::Pages);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        $identities = [];

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->isLink() || $item->getFilename() !== 'pagination.yml') {
                continue;
            }

            $directory = dirname($item->getPathname());
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($directory, strlen($root) + 1));
            $identities[] = PageIdentity::fromString($relative);
        }

        usort(
            $identities,
            static fn(PageIdentity $left, PageIdentity $right): int => $left->value() <=> $right->value(),
        );

        return array_map(
            fn(PageIdentity $identity): Collection => $this->get($identity, $languages),
            $identities,
        );
    }

    public function get(PageIdentity $identity, LanguageConfig $languages): Collection
    {
        if ($identity->isHomepage()) {
            throw new InvalidContentException('The homepage cannot be a collection.');
        }

        $relativePath = RelativePath::fromString($identity->value() . '/pagination.yml');

        try {
            $document = $this->yaml->read(FilesystemRoot::Pages, $relativePath);
            $directory = dirname($this->paths->resolve(FilesystemRoot::Pages, $relativePath, mustExist: true));
            if (is_file($directory . '/content.yml') || is_link($directory . '/content.yml')) {
                throw new InvalidArgumentException('A directory cannot be both a page and a collection.');
            }

            $absolutePath = $directory . '/pagination.yml';
            clearstatcache(true, $absolutePath);
            $modifiedAt = filemtime($absolutePath);
            if ($modifiedAt === false) {
                throw new InvalidArgumentException('Unable to read pagination.yml modification time.');
            }

            $data = $document->data();
            if (ContentData::integer($data['schemaVersion'] ?? null, 'schemaVersion') !== 1) {
                throw new InvalidArgumentException('Unsupported collection schema version.');
            }
            if (ContentData::string($data['type'] ?? null, 'type') !== 'collection') {
                throw new InvalidArgumentException('Collection type must be "collection".');
            }
            if (ContentData::string($data['source'] ?? null, 'source') !== 'children') {
                throw new InvalidArgumentException('Only the "children" collection source is supported.');
            }

            $sort = ContentData::map($data['sort'] ?? null, 'sort');
            $sortField = ContentData::string($sort['field'] ?? null, 'sort.field');
            if (preg_match('/^[a-z][A-Za-z0-9_]*(?:\.[a-z][A-Za-z0-9_]*)*$/D', $sortField) !== 1) {
                throw new InvalidArgumentException('Collection sort field is invalid.');
            }
            $sortDirection = ContentData::string($sort['direction'] ?? null, 'sort.direction');
            if (!in_array($sortDirection, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException('Collection sort direction is invalid.');
            }

            $pagination = ContentData::map($data['pagination'] ?? null, 'pagination');
            $perPage = ContentData::integer($pagination['perPage'] ?? null, 'pagination.perPage');
            if ($perPage < 1 || $perPage > 100) {
                throw new InvalidArgumentException('Collection perPage must be between 1 and 100.');
            }
            $layout = isset($data['layout']) ? ContentData::string($data['layout'], 'layout') : 'collection';
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $layout) !== 1) {
                throw new InvalidArgumentException('Collection layout name is invalid.');
            }

            return new Collection(
                $identity,
                ContentData::boolean($data['enabled'] ?? true, 'enabled'),
                $layout,
                $this->slugs($data['slug'] ?? null, $languages),
                $this->localizedStrings($data['title'] ?? null, 'title', $languages),
                isset($data['seo']) ? ContentData::map($data['seo'], 'seo') : [],
                $sortField,
                $sortDirection,
                $perPage,
                $this->filters($data['filters'] ?? []),
                $document->revision(),
                $modifiedAt,
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidContentException(
                sprintf('Collection "%s" contains invalid configuration.', $identity->value()),
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
        $localized = [];
        foreach ($languages->codes() as $locale) {
            $localized[$locale] = ContentData::string($mapping[$locale] ?? null, $field . '.' . $locale);
        }

        return $localized;
    }

    /** @return list<CollectionFilter> */
    private function filters(mixed $value): array
    {
        $filters = [];
        $parameters = [];
        foreach (ContentData::list($value, 'filters') as $index => $item) {
            $path = 'filters.' . $index;
            $definition = ContentData::map($item, $path);
            $parameter = ContentData::string($definition['parameter'] ?? null, $path . '.parameter');
            $field = ContentData::string($definition['field'] ?? null, $path . '.field');
            if (preg_match('/^[a-z][a-z0-9_-]*$/D', $parameter) !== 1) {
                throw new InvalidArgumentException('Collection filter parameter is invalid.');
            }
            if (in_array($parameter, ['lang', 'page'], true)) {
                throw new InvalidArgumentException('Collection filter parameter is reserved.');
            }
            if (preg_match('/^[a-z][A-Za-z0-9_]*(?:\.[a-z][A-Za-z0-9_]*)*$/D', $field) !== 1) {
                throw new InvalidArgumentException('Collection filter field is invalid.');
            }
            if (isset($parameters[$parameter])) {
                throw new InvalidArgumentException('Collection filter parameters must be unique.');
            }
            $parameters[$parameter] = true;

            $allowedValues = [];
            foreach (ContentData::list($definition['allowedValues'] ?? [], $path . '.allowedValues') as $value) {
                $allowedValues[] = ContentData::string($value, $path . '.allowedValues');
            }

            $filters[] = new CollectionFilter($parameter, $field, $allowedValues);
        }

        return $filters;
    }
}
