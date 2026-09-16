<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Seo;

use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Http\Request;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Media\MediaInspector;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaUrlGenerator;
use FlatFileCms\Media\SvgSanitizer;
use FlatFileCms\Seo\SeoResolver;
use FlatFileCms\Seo\SitemapController;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\TestCase;

final class SitemapControllerTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('config/languages.yml', "default: pl\nlanguages:\n  pl: { name: Polski, enabled: true }\n  en: { name: English, enabled: true }\n");
        $this->project->write('config/setup.yml', "schemaVersion: 1\nsite: { name: Example, url: 'https://example.test', defaultLayout: default }\nseo: {}\nmedia: {}\n");
        $this->project->write('pages/homepage/content.yml', "schemaVersion: 1\nenabled: true\ntitle: { pl: Start, en: Home }\nblocks: []\n");
        $this->project->write('pages/offer/content.yml', "schemaVersion: 1\nenabled: true\nslug: { pl: oferta, en: services }\ntitle: { pl: Oferta, en: Services }\nblocks:\n  - id: 01994d31-4fd1-7f32-9c2a-e89d624cda37\n    type: hero\n    enabled: true\n    data:\n      image: { src: cover.svg }\n");
        $this->project->write('pages/offer/cover.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" /></svg>');
        $this->project->write('pages/hidden/content.yml', "schemaVersion: 1\nenabled: false\nslug: { pl: ukryta, en: hidden }\ntitle: { pl: Ukryta, en: Hidden }\nblocks: []\n");
        $this->project->write('pages/private/content.yml', "schemaVersion: 1\nenabled: true\nslug: { pl: prywatna, en: private }\ntitle: { pl: Prywatna, en: Private }\nseo:\n  robots: { index: false, follow: true }\nblocks: []\n");
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItContainsOnlyEnabledLocalizedRoutes(): void
    {
        $paths = new SafePathResolver($this->project->path());
        $yaml = TestContentFactory::yaml($this->project);
        $configuration = new ConfigurationRepository($yaml, $paths);
        $media = new MediaRepository($paths, $configuration, new MediaInspector(new SvgSanitizer()));
        $controller = new SitemapController(
            new LanguageRepository($yaml, $paths),
            $configuration,
            new PageRepository($yaml, $paths),
            new CollectionRepository($yaml, $paths),
            new SeoResolver(new LocalizedDataResolver()),
            $media,
            new MediaUrlGenerator(),
        );
        $response = $controller->show(new Request('GET', '/sitemap.xml'));

        self::assertSame('application/xml; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringContainsString('<loc>https://example.test/pl/</loc>', $response->body());
        self::assertStringContainsString('<loc>https://example.test/en/</loc>', $response->body());
        self::assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $response->body());
        self::assertStringContainsString(
            'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"',
            $response->body(),
        );
        self::assertStringContainsString('<changefreq>daily</changefreq>', $response->body());
        self::assertStringContainsString('<priority>1.0</priority>', $response->body());
        self::assertStringContainsString('<changefreq>weekly</changefreq>', $response->body());
        self::assertStringContainsString('<priority>0.8</priority>', $response->body());
        self::assertStringContainsString(
            '<xhtml:link rel="alternate" hreflang="pl" href="https://example.test/pl/" />',
            $response->body(),
        );
        self::assertStringContainsString(
            '<xhtml:link rel="alternate" hreflang="en" href="https://example.test/en/" />',
            $response->body(),
        );
        self::assertStringContainsString(
            '<xhtml:link rel="alternate" hreflang="x-default" href="https://example.test/pl/" />',
            $response->body(),
        );
        self::assertStringContainsString(
            '<xhtml:link rel="alternate" hreflang="pl" href="https://example.test/pl/oferta" />',
            $response->body(),
        );
        self::assertStringContainsString(
            '<xhtml:link rel="alternate" hreflang="en" href="https://example.test/en/services" />',
            $response->body(),
        );
        self::assertMatchesRegularExpression(
            '#<image:loc>https://example\.test/media/offer/[a-f0-9]{16}/cover\.svg</image:loc>#',
            $response->body(),
        );
        self::assertStringNotContainsString('hidden', $response->body());
        self::assertStringNotContainsString('ukryta', $response->body());
        self::assertStringNotContainsString('private', $response->body());
        self::assertStringNotContainsString('prywatna', $response->body());
    }
}
