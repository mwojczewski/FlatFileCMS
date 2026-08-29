<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Api;

use FlatFileCms\Api\ApiResponseFactory;
use FlatFileCms\Api\PublicApiController;
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

    private function writeFixtures(): void
    {
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
YAML);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        return ContentData::map($data, 'response');
    }
}
