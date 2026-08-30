<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Content;

use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Content\PageNotFoundException;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Content\PageRouteIndex;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageRepository::class)]
#[CoversClass(PageRouteIndex::class)]
final class PageRouteIndexTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('config/languages.yml', <<<'YAML'
default: pl
languages:
  pl: { name: Polski, enabled: true }
  en: { name: English, enabled: true }
YAML);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItBuildsLocalizedNestedRoutesAndPrefixesUrls(): void
    {
        $this->writePage('homepage', null, 'Start', 'Home');
        $this->writePage('services', ['pl' => 'oferta', 'en' => 'services'], 'Oferta', 'Services');
        $this->writePage(
            'services/websites',
            ['pl' => 'strony-www', 'en' => 'websites'],
            'Strony WWW',
            'Websites',
        );
        [$languages, $routes] = $this->routes();

        $page = $routes->resolve('oferta/strony-www', 'pl');

        self::assertSame('services/websites', $page->identity()->value());
        self::assertSame('/en/services/websites', $routes->urlFor($page->identity(), 'en'));
        self::assertTrue($languages->isMultilingual());
    }

    public function testDisabledPageBehavesAsMissingPublicPage(): void
    {
        $this->writePage('homepage', null, 'Start', 'Home');
        $this->writePage('hidden', ['pl' => 'ukryta', 'en' => 'hidden'], 'Ukryta', 'Hidden', false);
        [, $routes] = $this->routes();

        $this->expectException(PageNotFoundException::class);
        $routes->resolve('ukryta', 'pl');
    }

    public function testSingleLanguageUrlsDoNotContainLocalePrefix(): void
    {
        $this->project->write('config/languages.yml', <<<'YAML'
default: pl
languages:
  pl: { name: Polski, enabled: true }
YAML);
        $this->writePage('homepage', null, 'Start', 'Home');
        $this->writePage('services', ['pl' => 'oferta', 'en' => 'services'], 'Oferta', 'Services');
        [, $routes] = $this->routes();

        self::assertSame('/oferta', $routes->urlFor(PageIdentity::fromString('services'), 'pl'));
    }

    public function testItRejectsLocalizedRouteCollisions(): void
    {
        $this->writePage('homepage', null, 'Start', 'Home');
        $this->writePage('first', ['pl' => 'duplikat', 'en' => 'first'], 'Pierwsza', 'First');
        $this->writePage('second', ['pl' => 'duplikat', 'en' => 'second'], 'Druga', 'Second');

        $this->expectException(InvalidContentException::class);
        $this->routes();
    }

    /** @return array{LanguageConfig, PageRouteIndex} */
    private function routes(): array
    {
        $yaml = TestContentFactory::yaml($this->project);
        $paths = new SafePathResolver($this->project->path());
        $languages = (new LanguageRepository($yaml, $paths))->get();
        $pages = (new PageRepository($yaml, $paths))->all($languages);

        return [$languages, PageRouteIndex::build($pages, $languages)];
    }

    /** @param array{pl: string, en: string}|null $slugs */
    private function writePage(
        string $identity,
        ?array $slugs,
        string $polishTitle,
        string $englishTitle,
        bool $enabled = true,
    ): void {
        $slugYaml = $slugs === null
            ? ''
            : \sprintf("slug:\n  pl: %s\n  en: %s\n", $slugs['pl'], $slugs['en']);
        $this->project->write(
            'pages/' . $identity . '/content.yml',
            \sprintf(
                "schemaVersion: 1\nenabled: %s\n%stitle:\n  pl: %s\n  en: %s\nblocks: []\n",
                $enabled ? 'true' : 'false',
                $slugYaml,
                $polishTitle,
                $englishTitle,
            ),
        );
    }
}
