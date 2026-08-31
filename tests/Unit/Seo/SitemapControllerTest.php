<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Seo;

use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Http\Request;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
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
        $this->project->write('pages/hidden/content.yml', "schemaVersion: 1\nenabled: false\nslug: { pl: ukryta, en: hidden }\ntitle: { pl: Ukryta, en: Hidden }\nblocks: []\n");
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItContainsOnlyEnabledLocalizedRoutes(): void
    {
        $paths = new SafePathResolver($this->project->path());
        $yaml = TestContentFactory::yaml($this->project);
        $controller = new SitemapController(
            new LanguageRepository($yaml, $paths),
            new ConfigurationRepository($yaml, $paths),
            new PageRepository($yaml, $paths),
            new CollectionRepository($yaml, $paths),
        );
        $response = $controller->show(new Request('GET', '/sitemap.xml'));

        self::assertSame('application/xml; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringContainsString('<loc>https://example.test/pl/</loc>', $response->body());
        self::assertStringContainsString('<loc>https://example.test/en/</loc>', $response->body());
        self::assertStringNotContainsString('hidden', $response->body());
        self::assertStringNotContainsString('ukryta', $response->body());
    }
}
