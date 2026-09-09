<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Admin;

use FlatFileCms\Admin\BlockFormRenderer;
use FlatFileCms\Blocks\BlockDefinition;
use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Domain\Localization\LanguageConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockFormRenderer::class)]
final class BlockFormRendererTest extends TestCase
{
    public function testMarkdownFieldsAreMarkedForLocalEasyMdeInitialization(): void
    {
        $definition = new BlockDefinition(
            'text',
            ['pl' => 'Tekst'],
            [],
            null,
            ['content' => new FieldDefinition('content', 'markdown', false, true, [])],
            '/blocks/text',
            '/blocks/text/render.php',
            1,
        );

        $html = (new BlockFormRenderer())->render(
            $definition,
            new LanguageConfig('pl', ['pl' => 'Polski', 'en' => 'English']),
            ['content' => ['pl' => '# Treść', 'en' => '# Content']],
        );

        self::assertSame(2, substr_count($html, 'data-markdown-editor'));
        self::assertStringContainsString('class="markdown-input"', $html);
        self::assertStringContainsString('name="data[content][pl]"', $html);
    }

    public function testUrlFieldsAllowRelativePathsInTheBrowser(): void
    {
        $definition = new BlockDefinition(
            'call-to-action',
            ['pl' => 'Wezwanie do działania'],
            [],
            null,
            ['button_url' => new FieldDefinition('button_url', 'url', true, false, [])],
            '/blocks/call-to-action',
            '/blocks/call-to-action/render.php',
            1,
        );

        $html = (new BlockFormRenderer())->render(
            $definition,
            new LanguageConfig('pl', ['pl' => 'Polski']),
            ['button_url' => '/en/documentation/getting-started'],
        );

        self::assertStringContainsString('type="text"', $html);
        self::assertStringContainsString('inputmode="url"', $html);
        self::assertStringContainsString('value="/en/documentation/getting-started"', $html);
        self::assertStringNotContainsString('type="url"', $html);
    }
}
