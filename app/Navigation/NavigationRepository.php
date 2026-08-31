<?php

declare(strict_types=1);

namespace FlatFileCms\Navigation;

use FlatFileCms\Collections\CollectionNotFoundException;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageNotFoundException;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlDocument;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class NavigationRepository
{
    private const string FILE = 'navigation.yml';
    private const int MAX_DEPTH = 8;
    private const int MAX_ITEMS = 250;

    public function __construct(
        private YamlFileRepository $yaml,
        private SafePathResolver $paths,
        private LocalizedDataResolver $localization,
    ) {}

    public function resolve(
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
    ): NavigationDocument {
        $document = $this->raw();

        try {
            $menus = $this->resolveData($document->data(), $locale, $languages, $routes);
            $absolutePath = $this->paths->resolve(
                FilesystemRoot::Config,
                RelativePath::fromString(self::FILE),
                mustExist: true,
            );
            clearstatcache(true, $absolutePath);
            $modifiedAt = filemtime($absolutePath);
            if ($modifiedAt === false) {
                throw new InvalidArgumentException('Unable to read navigation.yml modification time.');
            }

            return new NavigationDocument($menus, $document->revision(), $modifiedAt);
        } catch (CollectionNotFoundException | InvalidArgumentException | PageNotFoundException $exception) {
            throw new InvalidContentException('Invalid navigation.yml configuration.', previous: $exception);
        }
    }

    public function raw(): YamlDocument
    {
        return $this->yaml->read(FilesystemRoot::Config, RelativePath::fromString(self::FILE));
    }

    /** @param array<string, mixed> $data */
    public function update(
        array $data,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
        PageRouteIndex $routes,
    ): NavigationDocument {
        try {
            foreach ($languages->codes() as $locale) {
                $this->resolveData($data, $locale, $languages, $routes);
            }
        } catch (CollectionNotFoundException|InvalidArgumentException|PageNotFoundException $exception) {
            throw new InvalidContentException('Invalid navigation data.', previous: $exception);
        }

        $this->yaml->write(
            FilesystemRoot::Config,
            RelativePath::fromString(self::FILE),
            $data,
            $expectedRevision,
        );

        return $this->resolve($languages->default(), $languages, $routes);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, list<array<string, mixed>>>
     */
    private function resolveData(
        array $data,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
    ): array {
        if ($data === []) {
            throw new InvalidArgumentException('Navigation requires at least one menu.');
        }

        $menus = [];
        $itemCount = 0;
        foreach ($data as $name => $items) {
            if (preg_match('/^[a-z][a-z0-9_-]*$/D', $name) !== 1) {
                throw new InvalidArgumentException('Navigation menu name is invalid.');
            }
            $menus[$name] = $this->items($items, $locale, $languages, $routes, $name, 1, $itemCount);
        }

        return $menus;
    }

    /** @return list<array<string, mixed>> */
    private function items(
        mixed $value,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
        string $field,
        int $depth,
        int &$itemCount,
    ): array {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Navigation nesting is too deep.');
        }
        $resolved = [];
        foreach (ContentData::list($value, $field) as $index => $item) {
            if (++$itemCount > self::MAX_ITEMS) {
                throw new InvalidArgumentException('Navigation contains too many items.');
            }
            $entry = ContentData::map($item, $field . '.' . $index);
            foreach (array_keys($entry) as $property) {
                if (!\in_array($property, ['label', 'link', 'url', 'target', 'children'], true)) {
                    throw new InvalidArgumentException("Unknown navigation property \"{$property}\".");
                }
            }
            $this->labels($entry['label'] ?? null, $languages);
            $label = $this->localization->resolve($entry['label'] ?? null, $locale, $languages);
            $target = isset($entry['target']) ? ContentData::string($entry['target'], 'target') : '_self';
            if (!\in_array($target, ['_self', '_blank'], true)) {
                throw new InvalidArgumentException('Navigation target is invalid.');
            }

            $resolved[] = [
                'label' => ContentData::string($label, 'label'),
                'url' => $this->url($entry, $locale, $routes),
                'target' => $target,
                'children' => $this->items(
                    $entry['children'] ?? [],
                    $locale,
                    $languages,
                    $routes,
                    "{$field}.{$index}.children",
                    $depth + 1,
                    $itemCount,
                ),
            ];
        }

        return $resolved;
    }

    /** @param array<string, mixed> $entry */
    private function url(array $entry, string $locale, PageRouteIndex $routes): string
    {
        if (isset($entry['link'])) {
            if (isset($entry['url'])) {
                throw new InvalidArgumentException('Navigation item cannot define both link and url.');
            }
            $link = ContentData::map($entry['link'], 'link');
            $type = ContentData::string($link['type'] ?? null, 'link.type');
            $allowed = match ($type) {
                'page' => ['type', 'page'],
                'collection' => ['type', 'collection'],
                'url' => ['type', 'url'],
                default => throw new InvalidArgumentException('Navigation link type is invalid.'),
            };
            foreach (array_keys($link) as $property) {
                if (!\in_array($property, $allowed, true)) {
                    throw new InvalidArgumentException("Unknown navigation link property \"{$property}\".");
                }
            }

            return match ($type) {
                'page' => $routes->urlFor(
                    PageIdentity::fromString(ContentData::string($link['page'] ?? null, 'link.page')),
                    $locale,
                ),
                'collection' => $routes->collectionUrlFor(
                    PageIdentity::fromString(ContentData::string($link['collection'] ?? null, 'link.collection')),
                    $locale,
                ),
                'url' => $this->externalUrl($link['url'] ?? null),
            };
        }

        return $this->externalUrl($entry['url'] ?? null);
    }

    private function externalUrl(mixed $value): string
    {
        $url = ContentData::string($value, 'url');
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!\is_string($scheme) || !\in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
            throw new InvalidArgumentException('Navigation URL is invalid.');
        }
        if (\in_array(strtolower($scheme), ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Navigation URL is invalid.');
        }

        return $url;
    }

    private function labels(mixed $value, LanguageConfig $languages): void
    {
        $labels = ContentData::map($value, 'label');
        foreach ($labels as $locale => $label) {
            if (!$languages->has($locale) || trim(ContentData::string($label, "label.{$locale}")) === '') {
                throw new InvalidArgumentException('Navigation label contains an invalid locale or value.');
            }
        }
        if (!isset($labels[$languages->default()])) {
            throw new InvalidArgumentException('Navigation label requires the default locale.');
        }
    }
}
