<?php

declare(strict_types=1);

namespace FlatFileCms\Seo;

use DateTimeImmutable;
use DateTimeZone;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\ConfigurationDocument;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Media\MediaConfig;
use FlatFileCms\Media\MediaException;
use FlatFileCms\Media\MediaName;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaTypes;
use FlatFileCms\Media\MediaUrlGenerator;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class SitemapController
{
    public function __construct(
        private LanguageRepository $languages,
        private ConfigurationRepository $configuration,
        private PageRepository $pages,
        private CollectionRepository $collections,
        private SeoResolver $seo,
        private MediaRepository $media,
        private MediaUrlGenerator $mediaUrls,
    ) {}

    public function show(Request $request): Response
    {
        $languages = $this->languages->get();
        $pages = $this->pages->all($languages);
        $collections = $this->collections->all($languages);
        $routes = PageRouteIndex::build($pages, $languages, $collections);
        $configuration = $this->configuration->get();
        $site = ContentData::map($configuration->data()['site'] ?? null, 'site');
        $baseUrl = rtrim(ContentData::string($site['url'] ?? null, 'site.url'), '/');
        $mediaConfig = MediaConfig::fromDocument($configuration);
        [$imageWidth, $imageHeight] = $this->imageDimensions($configuration, $mediaConfig);
        $entries = [];

        foreach ($pages as $page) {
            if ($page->enabled()) {
                $localizedUrls = [];
                foreach ($languages->codes() as $locale) {
                    $path = $routes->urlFor($page->identity(), $locale);
                    $resolvedSeo = $this->seo->resolve($page, $locale, $path, $languages, $configuration);
                    if (self::isIndexable($resolvedSeo)) {
                        $localizedUrls[$locale] = $path;
                    }
                }

                $alternates = $localizedUrls;
                if (isset($localizedUrls[$languages->default()])) {
                    $alternates['x-default'] = $routes->urlFor($page->identity(), $languages->default());
                }
                $images = $this->images(
                    $page->identity(),
                    $page->blocks(),
                    $baseUrl,
                    $mediaConfig,
                    $imageWidth,
                    $imageHeight,
                );

                foreach ($localizedUrls as $path) {
                    $entries[$path] = [
                        'modifiedAt' => $page->modifiedAt(),
                        'changefreq' => $page->identity()->isHomepage() ? 'daily' : 'weekly',
                        'priority' => $page->identity()->isHomepage() ? '1.0' : '0.8',
                        'alternates' => $alternates,
                        'images' => $images,
                    ];
                }
            }
        }
        foreach ($collections as $collection) {
            if ($collection->enabled()) {
                $localizedUrls = [];
                foreach ($languages->codes() as $locale) {
                    $path = $routes->collectionUrlFor($collection->identity(), $locale);
                    $resolvedSeo = $this->seo->resolveCollection(
                        $collection,
                        $locale,
                        $path,
                        $languages,
                        $configuration,
                    );
                    if (self::isIndexable($resolvedSeo)) {
                        $localizedUrls[$locale] = $path;
                    }
                }

                $alternates = $localizedUrls;
                if (isset($localizedUrls[$languages->default()])) {
                    $alternates['x-default'] = $routes->collectionUrlFor(
                        $collection->identity(),
                        $languages->default(),
                    );
                }

                foreach ($localizedUrls as $path) {
                    $entries[$path] = [
                        'modifiedAt' => $collection->modifiedAt(),
                        'changefreq' => 'daily',
                        'priority' => '0.8',
                        'alternates' => $alternates,
                        'images' => [],
                    ];
                }
            }
        }
        ksort($entries);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:xhtml="http://www.w3.org/1999/xhtml"'
            . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($entries as $path => $entry) {
            $location = $baseUrl . ($path === '/' ? '/' : $path);
            $lastModified = (new DateTimeImmutable("@{$entry['modifiedAt']}"))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d');
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . self::xml($location) . "</loc>\n";
            $xml .= "    <lastmod>{$lastModified}</lastmod>\n";
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$entry['priority']}</priority>\n";
            foreach ($entry['alternates'] as $alternateLocale => $alternatePath) {
                $alternateLocation = $baseUrl . ($alternatePath === '/' ? '/' : $alternatePath);
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . self::xml($alternateLocale)
                    . '" href="' . self::xml($alternateLocation) . '" />' . "\n";
            }
            foreach ($entry['images'] as $image) {
                $xml .= "    <image:image>\n";
                $xml .= '      <image:loc>' . self::xml($image) . "</image:loc>\n";
                $xml .= "    </image:image>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return new Response($xml, headers: [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, mixed> $seo */
    private static function isIndexable(array $seo): bool
    {
        $robots = $seo['robots'] ?? null;

        return !\is_array($robots) || ($robots['index'] ?? true) === true;
    }

    /** @return array{int, int} */
    private function imageDimensions(ConfigurationDocument $configuration, MediaConfig $media): array
    {
        $seo = ContentData::map($configuration->data()['seo'] ?? [], 'seo');
        $sitemap = ContentData::map($seo['sitemap'] ?? [], 'seo.sitemap');
        $images = ContentData::map($sitemap['images'] ?? [], 'seo.sitemap.images');
        $width = ContentData::integer($images['maxWidth'] ?? 1280, 'seo.sitemap.images.maxWidth');
        $height = ContentData::integer($images['maxHeight'] ?? 720, 'seo.sitemap.images.maxHeight');
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Sitemap image dimensions must be positive.');
        }

        return [min($width, $media->maximumWidth()), min($height, $media->maximumHeight())];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<string>
     */
    private function images(
        PageIdentity $identity,
        array $blocks,
        string $baseUrl,
        MediaConfig $config,
        int $width,
        int $height,
    ): array {
        $images = [];
        foreach ($blocks as $block) {
            if (($block['enabled'] ?? true) !== true) {
                continue;
            }
            $this->collectImages($identity, $block['data'] ?? [], $baseUrl, $config, $width, $height, $images);
        }

        ksort($images);

        return array_values($images);
    }

    /** @param array<string, string> $images */
    private function collectImages(
        PageIdentity $identity,
        mixed $value,
        string $baseUrl,
        MediaConfig $config,
        int $width,
        int $height,
        array &$images,
    ): void {
        if (!\is_array($value)) {
            return;
        }
        $source = $value['src'] ?? null;
        if (\is_string($source)) {
            try {
                $item = $this->media->get($identity, MediaName::fromString($source))->item();
                if ($item->isImage()) {
                    $path = $config->transformationsEnabled()
                        && $config->allowsFormat('webp')
                        && MediaTypes::isTransformable($item->mimeType())
                        ? $this->mediaUrls->variant($identity, $item, $width, $height, 'webp')
                        : $this->mediaUrls->original($identity, $item);
                    $images[$path] = $baseUrl . $path;
                }
            } catch (InvalidArgumentException|MediaException) {
                // Invalid media references are reported by regular page validation.
            }
        }
        foreach ($value as $child) {
            $this->collectImages($identity, $child, $baseUrl, $config, $width, $height, $images);
        }
    }
}
