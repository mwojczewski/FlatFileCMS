<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Api;

use FlatFileCms\Api\ApiResponseFactory;
use FlatFileCms\Api\PublicApiController;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicApiController::class)]
#[CoversClass(ApiResponseFactory::class)]
final class PublicApiControllerTest extends TestCase
{
    private TemporaryProject $project;
    private PublicApiController $controller;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->writeFixtures();
        $this->controller = TestContentFactory::publicApi($this->project);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItReturnsLocalizedNormalizedPageWithSeoFallbacks(): void
    {
        $request = new Request(
            'GET',
            '/api/v1/pages/services',
            query: ['lang' => 'en'],
            attributes: ['path' => 'services'],
        );

        $response = $this->controller->page($request);
        $data = $this->decode($response);
        $blocks = ContentData::list($data['blocks'] ?? null, 'blocks');
        $firstBlock = ContentData::map($blocks[0] ?? null, 'blocks.0');
        $blockData = ContentData::map($firstBlock['data'] ?? null, 'blocks.0.data');
        $seo = ContentData::map($data['seo'] ?? null, 'seo');

        self::assertSame(200, $response->status());
        self::assertSame('Services', $data['title']);
        self::assertSame('/en/services', $data['url']);
        self::assertSame('Welcome', $blockData['heading']);
        $image = ContentData::map($blockData['image'] ?? null, 'blocks.0.data.image');
        self::assertSame('hero.png', $image['src']);
        self::assertSame('image/png', $image['mimeType']);
        self::assertSame(1, $image['width']);
        self::assertSame(1, $image['height']);
        self::assertMatchesRegularExpression(
            '#^/media/services/[a-f0-9]{16}/hero\.png$#D',
            ContentData::string($image['url'] ?? null, 'blocks.0.data.image.url'),
        );
        self::assertSame('Services — Example', $seo['title']);
        self::assertSame('Global description', $seo['description']);
        self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/D', $response->headers()['ETag']);
    }

    public function testItReturnsNotModifiedForMatchingEtag(): void
    {
        $request = new Request(
            'GET',
            '/api/v1/pages/services',
            query: ['lang' => 'pl'],
            attributes: ['path' => 'oferta'],
        );
        $first = $this->controller->page($request);
        $conditional = new Request(
            'GET',
            '/api/v1/pages/services',
            headers: ['if-none-match' => $first->headers()['ETag']],
            query: ['lang' => 'pl'],
            attributes: ['path' => 'oferta'],
        );

        $second = $this->controller->page($conditional);

        self::assertSame(304, $second->status());
        self::assertSame('', $second->body());
    }

    public function testMediaChangeInvalidatesPageLastModifiedValidator(): void
    {
        $request = new Request(
            'GET',
            '/api/v1/pages/services',
            query: ['lang' => 'en'],
            attributes: ['path' => 'services'],
        );
        $first = $this->controller->page($request);
        $path = $this->project->path('pages/services/hero.png');
        $contents = \file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Test image fixture cannot be read.');
        }
        $this->project->write('pages/services/hero.png', $contents . 'changed');
        \touch($path, \time() + 2);

        $second = $this->controller->page(new Request(
            'GET',
            '/api/v1/pages/services',
            headers: ['if-modified-since' => $first->headers()['Last-Modified']],
            query: ['lang' => 'en'],
            attributes: ['path' => 'services'],
        ));

        self::assertSame(200, $second->status());
        self::assertNotSame($first->body(), $second->body());
    }

    public function testItResolvesNavigationPageReferencesForLocale(): void
    {
        $response = $this->controller->navigation(new Request(
            'GET',
            '/api/v1/navigation',
            query: ['lang' => 'en'],
        ));
        $data = $this->decode($response);
        $menus = ContentData::map($data['menus'] ?? null, 'menus');
        $main = ContentData::list($menus['main'] ?? null, 'menus.main');
        $firstItem = ContentData::map($main[0] ?? null, 'menus.main.0');

        self::assertSame('/en/services', $firstItem['url']);
        self::assertSame('Services', $firstItem['label']);
    }

    public function testItDoesNotExposeDisabledPage(): void
    {
        $this->project->write('pages/services/content.yml', <<<'YAML'
schemaVersion: 1
enabled: false
slug: { pl: oferta, en: services }
title: { pl: Oferta, en: Services }
blocks: []
YAML);
        $this->controller = TestContentFactory::publicApi($this->project);

        try {
            $this->controller->page(new Request(
                'GET',
                '/api/v1/pages/services',
                query: ['lang' => 'en'],
                attributes: ['path' => 'services'],
            ));
            self::fail('Expected disabled page to be hidden.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->status());
            self::assertSame('PAGE_NOT_FOUND', $exception->errorCode());
        }
    }

    public function testItRejectsDuplicateBlockIdentifiers(): void
    {
        $this->project->write('pages/services/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
slug: { pl: oferta, en: services }
title: { pl: Oferta, en: Services }
blocks:
  - id: 01994d31-4fd1-7f32-9c2a-e89d624cda37
    type: hero
    data:
      heading: { pl: Pierwszy, en: First }
  - id: 01994d31-4fd1-7f32-9c2a-e89d624cda37
    type: hero
    data:
      heading: { pl: Drugi, en: Second }
YAML);
        $this->controller = TestContentFactory::publicApi($this->project);

        $this->expectException(InvalidContentException::class);
        $this->controller->page(new Request(
            'GET',
            '/api/v1/pages/services',
            query: ['lang' => 'en'],
            attributes: ['path' => 'services'],
        ));
    }

    private function writeFixtures(): void
    {
        $this->project->write('blocks/hero/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Hero, en: Hero }
fields:
  heading:
    type: text
    required: true
    translatable: true
    minLength: 1
    maxLength: 160
  image:
    type: image
    required: true
    translatable: false
YAML);
        $this->project->write('blocks/hero/render.php', "<?php\n\ndeclare(strict_types=1);\n");
        $this->project->write('config/languages.yml', <<<'YAML'
default: pl
languages:
  pl: { name: Polski, enabled: true }
  en: { name: English, enabled: true }
YAML);
        $this->project->write('config/setup.yml', <<<'YAML'
schemaVersion: 1
site:
  name: Example
  url: https://example.com
  defaultLayout: default
seo:
  titleSuffix: Example
  description: Global description
media: { }
YAML);
        $this->project->write('config/navigation.yml', <<<'YAML'
main:
  - label: { pl: Oferta, en: Services }
    link: { type: page, page: services }
    target: _self
YAML);
        $this->project->write('pages/homepage/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
title: { pl: Start, en: Home }
blocks: []
YAML);
        $this->project->write('pages/services/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
slug: { pl: oferta, en: services }
title: { pl: Oferta, en: Services }
seo:
  title: { pl: Oferta, en: Services }
blocks:
  - id: 01994d31-4fd1-7f32-9c2a-e89d624cda37
    type: hero
    enabled: true
    data:
      heading: { pl: Witaj, en: Welcome }
      image:
        src: hero.png
        alt: { pl: Bohater, en: Hero }
YAML);
        $image = \base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        if ($image === false) {
            throw new \RuntimeException('Test image fixture is invalid.');
        }
        $this->project->write('pages/services/hero.png', $image);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        return ContentData::map($data, 'response');
    }
}
