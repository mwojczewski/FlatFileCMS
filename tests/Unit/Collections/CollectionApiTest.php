<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Collections;

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
final class CollectionApiTest extends TestCase
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

    public function testItFiltersSortsAndPaginatesDirectChildren(): void
    {
        $response = $this->controller->collection(new Request(
            'GET',
            '/api/v1/collections/blog',
            query: ['lang' => 'en', 'category' => 'tech', 'page' => '1'],
            attributes: ['path' => 'blog'],
        ));
        $data = $this->decode($response);
        $items = ContentData::list($data['items'] ?? null, 'items');
        $first = ContentData::map($items[0] ?? null, 'items.0');
        $second = ContentData::map($items[1] ?? null, 'items.1');
        $pagination = ContentData::map($data['pagination'] ?? null, 'pagination');
        $attributes = ContentData::map($first['attributes'] ?? null, 'items.0.attributes');

        self::assertSame(200, $response->status());
        self::assertSame('blog/third', $first['id']);
        self::assertSame('blog/first', $second['id']);
        self::assertSame('Newest excerpt', $attributes['excerpt']);
        self::assertSame(2, $pagination['totalItems']);
        self::assertSame(1, $pagination['totalPages']);
    }

    public function testAChildPageUsesItsCollectionLocalizedSlugAsAncestor(): void
    {
        $response = $this->controller->page(new Request(
            'GET',
            '/api/v1/pages/blog/third',
            query: ['lang' => 'pl'],
            attributes: ['path' => 'aktualnosci/najnowszy'],
        ));
        $data = $this->decode($response);

        self::assertSame(200, $response->status());
        self::assertSame('/pl/aktualnosci/najnowszy', $data['url']);
    }

    public function testItRejectsDisallowedFilterValues(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Value for filter "category" is not allowed.');

        $this->controller->collection(new Request(
            'GET',
            '/api/v1/collections/blog',
            query: ['category' => 'private'],
            attributes: ['path' => 'aktualnosci'],
        ));
    }

    public function testLanguageAddedAfterContentCreationUsesDefaultCollectionAndPageData(): void
    {
        $this->project->write('config/languages.yml', <<<'YAML'
default: pl
languages:
  pl: { name: Polski, enabled: true }
  en: { name: English, enabled: true }
  de: { name: Deutsch, enabled: true }
YAML);

        $response = $this->controller->page(new Request(
            'GET',
            '/api/v1/pages/aktualnosci/najnowszy',
            query: ['lang' => 'de'],
            attributes: ['path' => 'aktualnosci/najnowszy'],
        ));
        $data = $this->decode($response);

        self::assertSame(200, $response->status());
        self::assertSame('Wpis third', $data['title']);
        self::assertSame('/de/aktualnosci/najnowszy', $data['url']);
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
site: { name: Example, url: 'https://example.com', defaultLayout: default }
seo: { titleSuffix: Example, description: Description }
media: { }
YAML);
        $this->project->write('config/navigation.yml', "main: []\n");
        $this->project->write('pages/homepage/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
title: { pl: Start, en: Home }
blocks: []
YAML);
        $this->project->write('pages/blog/pagination.yml', <<<'YAML'
schemaVersion: 1
type: collection
enabled: true
layout: collection
slug: { pl: aktualnosci, en: blog }
title: { pl: Aktualności, en: Blog }
source: children
sort: { field: date, direction: desc }
pagination: { perPage: 2 }
filters:
  - parameter: category
    field: category
    allowedValues: [tech, company]
YAML);
        $this->writePage('first', 'pierwszy', 'first', '2026-01-01', 'tech', 'Pierwszy opis', 'First excerpt');
        $this->writePage('second', 'drugi', 'second', '2026-02-01', 'company', 'Drugi opis', 'Second excerpt');
        $this->writePage('third', 'najnowszy', 'third', '2026-03-01', 'tech', 'Najnowszy opis', 'Newest excerpt');
    }

    private function writePage(
        string $identity,
        string $plSlug,
        string $enSlug,
        string $date,
        string $category,
        string $plExcerpt,
        string $enExcerpt,
    ): void {
        $this->project->write('pages/blog/' . $identity . '/content.yml', <<<YAML
schemaVersion: 1
enabled: true
slug: { pl: {$plSlug}, en: {$enSlug} }
title: { pl: Wpis {$identity}, en: Post {$identity} }
date: {$date}
category: {$category}
excerpt: { pl: '{$plExcerpt}', en: '{$enExcerpt}' }
blocks: []
YAML);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        return ContentData::map($data, 'response');
    }
}
