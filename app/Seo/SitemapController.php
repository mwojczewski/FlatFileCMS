<?php

declare(strict_types=1);

namespace FlatFileCms\Seo;

use DateTimeImmutable;
use DateTimeZone;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Support\ContentData;

final readonly class SitemapController
{
    public function __construct(
        private LanguageRepository $languages,
        private ConfigurationRepository $configuration,
        private PageRepository $pages,
        private CollectionRepository $collections,
    ) {}

    public function show(Request $request): Response
    {
        $languages = $this->languages->get();
        $pages = $this->pages->all($languages);
        $collections = $this->collections->all($languages);
        $routes = PageRouteIndex::build($pages, $languages, $collections);
        $site = ContentData::map($this->configuration->get()->data()['site'] ?? null, 'site');
        $baseUrl = rtrim(ContentData::string($site['url'] ?? null, 'site.url'), '/');
        $entries = [];

        foreach ($languages->codes() as $locale) {
            foreach ($pages as $page) {
                if ($page->enabled()) {
                    $entries[$routes->urlFor($page->identity(), $locale)] = $page->modifiedAt();
                }
            }
            foreach ($collections as $collection) {
                if ($collection->enabled()) {
                    $entries[$routes->collectionUrlFor($collection->identity(), $locale)] = $collection->modifiedAt();
                }
            }
        }
        ksort($entries);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $path => $modifiedAt) {
            $location = $baseUrl . ($path === '/' ? '/' : $path);
            $lastModified = (new DateTimeImmutable("@{$modifiedAt}"))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d');
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . self::xml($location) . "</loc>\n";
            $xml .= "    <lastmod>{$lastModified}</lastmod>\n";
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
}
