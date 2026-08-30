<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Rendering;

use FlatFileCms\Rendering\MarkdownRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownRenderer::class)]
final class MarkdownRendererTest extends TestCase
{
    public function testItStripsHtmlAndRejectsUnsafeLinks(): void
    {
        $html = (new MarkdownRenderer())->render('<script>alert(1)</script> [link](javascript:alert(1))');

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('href="javascript:', $html);
    }
}
