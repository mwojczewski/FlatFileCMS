<?php

declare(strict_types=1);

namespace FlatFileCms\Navigation;

use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageNotFoundException;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class NavigationRepository
{
    private const string FILE = 'navigation.yml';

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
        $path = RelativePath::fromString(self::FILE);
        $document = $this->yaml->read(FilesystemRoot::Config, $path);

        try {
            $menus = [];
            foreach ($document->data() as $name => $items) {
                if (preg_match('/^[a-z][a-z0-9_-]*$/D', $name) !== 1) {
                    throw new InvalidArgumentException('Navigation menu name is invalid.');
                }

                $menus[$name] = $this->items($items, $locale, $languages, $routes, $name);
            }

            $absolutePath = $this->paths->resolve(FilesystemRoot::Config, $path, mustExist: true);
            clearstatcache(true, $absolutePath);
            $modifiedAt = filemtime($absolutePath);
            if ($modifiedAt === false) {
                throw new InvalidArgumentException('Unable to read navigation.yml modification time.');
            }

            return new NavigationDocument($menus, $document->revision(), $modifiedAt);
        } catch (InvalidArgumentException|PageNotFoundException $exception) {
            throw new InvalidContentException('Invalid navigation.yml configuration.', previous: $exception);
        }
    }

    /** @return list<array<string, mixed>> */
    private function items(
        mixed $value,
        string $locale,
        LanguageConfig $languages,
        PageRouteIndex $routes,
        string $field,
    ): array {
        $resolved = [];
        foreach (ContentData::list($value, $field) as $index => $item) {
            $entry = ContentData::map($item, $field . '.' . $index);
            $label = $this->localization->resolve($entry['label'] ?? null, $locale, $languages);
            $target = isset($entry['target']) ? ContentData::string($entry['target'], 'target') : '_self';
            if (!in_array($target, ['_self', '_blank'], true)) {
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
                    $field . '.' . $index . '.children',
                ),
            ];
        }

        return $resolved;
    }

    /** @param array<string, mixed> $entry */
    private function url(array $entry, string $locale, PageRouteIndex $routes): string
    {
        if (isset($entry['link'])) {
            $link = ContentData::map($entry['link'], 'link');
            $type = ContentData::string($link['type'] ?? null, 'link.type');

            return match ($type) {
                'page' => $routes->urlFor(
                    PageIdentity::fromString(ContentData::string($link['page'] ?? null, 'link.page')),
                    $locale,
                ),
                'url' => $this->externalUrl($link['url'] ?? null),
                default => throw new InvalidArgumentException('Navigation link type is invalid.'),
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
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
            throw new InvalidArgumentException('Navigation URL is invalid.');
        }
        if (in_array(strtolower($scheme), ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Navigation URL is invalid.');
        }

        return $url;
    }
}
