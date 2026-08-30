<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Rendering;

use FlatFileCms\Http\Request;
use FlatFileCms\Rendering\SiteController;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteController::class)]
final class SiteControllerTest extends TestCase
{
    private TemporaryProject $project;
    private SiteController $controller;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->writeFixtures();
        $this->controller = TestContentFactory::site($this->project);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItRedirectsUnprefixedMultilingualRoutes(): void
    {
        $homepage = $this->controller->homepage(new Request('GET', '/'));
        $page = $this->controller->page(new Request('GET', '/uslugi', attributes: ['path' => 'uslugi']));

        self::assertSame(302, $homepage->status());
        self::assertSame('/pl/', $homepage->headers()['Location']);
        self::assertSame(302, $page->status());
        self::assertSame('/pl/uslugi', $page->headers()['Location']);
    }

    public function testItRendersLocalizedValidatedPageAndOnlyItsAssets(): void
    {
        $response = $this->controller->page(new Request(
            'GET',
            '/en/services',
            attributes: ['path' => 'en/services'],
        ));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('<html lang="en">', $response->body());
        self::assertStringContainsString('<h2>Welcome</h2>', $response->body());
        self::assertStringContainsString('<strong>safe</strong>', $response->body());
        self::assertStringNotContainsString('<script>alert', $response->body());
        self::assertMatchesRegularExpression('#/assets/blocks/text/text\.[a-f0-9]{16}\.css#', $response->body());
        self::assertSame(1, substr_count($response->body(), '/assets/blocks/text/'));
        self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/D', $response->headers()['ETag']);
    }

    public function testItReturnsNotModifiedForMatchingEtag(): void
    {
        $request = new Request('GET', '/en/services', attributes: ['path' => 'en/services']);
        $first = $this->controller->page($request);
        $second = $this->controller->page(new Request(
            'GET',
            '/en/services',
            headers: ['if-none-match' => $first->headers()['ETag']],
            attributes: ['path' => 'en/services'],
        ));

        self::assertSame(304, $second->status());
        self::assertSame('', $second->body());
    }

    private function writeFixtures(): void
    {
        $this->project->write('blocks/text/block.yml', <<<'YAML'
schemaVersion: 1
name: { pl: Tekst, en: Text }
fields:
  title: { type: text, required: true, translatable: true }
  content: { type: markdown, required: true, translatable: true }
YAML);
        $this->project->write('blocks/text/render.php', <<<'PHP'
<?php

declare(strict_types=1);
?>
<section><h2><?= $context->escape($data['title']) ?></h2><?= $context->markdown($data['content']) ?></section>
PHP);
        $this->project->write('blocks/text/style.css', ".text { padding: 1rem; }\n");
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
        $this->project->write('pages/services/content.yml', <<<'YAML'
schemaVersion: 1
enabled: true
slug: { pl: uslugi, en: services }
title: { pl: Usługi, en: Services }
blocks:
  - id: 01994d31-4fd1-7f32-9c2a-e89d624cda37
    type: text
    data:
      title: { pl: Witaj, en: Welcome }
      content:
        pl: '**bezpieczna** treść'
        en: '**safe** content <script>alert(1)</script>'
YAML);
        $this->project->write('templates/layouts/default.php', <<<'PHP'
<?php

declare(strict_types=1);
?>
<!doctype html><html lang="<?= $context->escape($page->locale()) ?>"><body><?= $content ?><?php foreach ($assets->styles() as $style): ?><link rel="stylesheet" href="<?= $context->asset($style) ?>"><?php endforeach; ?></body></html>
PHP);
    }
}
