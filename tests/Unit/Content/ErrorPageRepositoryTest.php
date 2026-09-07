<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Content;

use FlatFileCms\Content\ErrorPageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorPageRepository::class)]
final class ErrorPageRepositoryTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItUsesTheStatusFamilyWhenAnExactPageDoesNotExist(): void
    {
        $this->writePage(400, 'Błąd żądania', 'Request error');

        $page = $this->repository()->find(404, $this->languages());

        self::assertNotNull($page);
        self::assertSame('400', $page->identity()->value());
        self::assertSame('Błąd żądania', $page->title('pl', 'pl'));
    }

    public function testAnExactStatusPageTakesPriorityOverTheFamilyPage(): void
    {
        $this->writePage(400, 'Błąd żądania', 'Request error');
        $this->writePage(404, 'Nie znaleziono', 'Not found');

        $page = $this->repository()->find(404, $this->languages());

        self::assertNotNull($page);
        self::assertSame('404', $page->identity()->value());
        self::assertSame('Not found', $page->title('en', 'pl'));
    }

    private function repository(): ErrorPageRepository
    {
        $yaml = TestContentFactory::yaml($this->project);
        $paths = new SafePathResolver($this->project->path());

        return new ErrorPageRepository($yaml, $paths, new PageRepository($yaml, $paths));
    }

    private function languages(): LanguageConfig
    {
        return new LanguageConfig('pl', ['pl' => 'Polski', 'en' => 'English']);
    }

    private function writePage(int $status, string $pl, string $en): void
    {
        $this->project->write("errors/{$status}/content.yml", <<<YAML
schemaVersion: 1
enabled: true
layout: default
title:
  pl: {$pl}
  en: {$en}
seo: {}
blocks: []
YAML);
    }
}
