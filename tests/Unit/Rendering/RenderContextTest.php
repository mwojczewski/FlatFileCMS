<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Rendering;

use FlatFileCms\Rendering\MarkdownRenderer;
use FlatFileCms\Rendering\OutputBuffer;
use FlatFileCms\Rendering\PartialRegistry;
use FlatFileCms\Rendering\PartialRenderer;
use FlatFileCms\Rendering\RenderContext;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderContext::class)]
final class RenderContextTest extends TestCase
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

    public function testCloudflareBeaconIsOmittedWithoutToken(): void
    {
        self::assertSame('', $this->context()->cloudflareBeacon());
    }

    public function testCloudflareBeaconContainsSafelyEncodedEnvironmentToken(): void
    {
        $html = $this->context('9204911a1d27459b9d4cff24a71bbd1f')->cloudflareBeacon();

        self::assertStringContainsString('https://static.cloudflareinsights.com/beacon.min.js', $html);
        self::assertStringContainsString(
            'data-cf-beacon="{&quot;token&quot;:&quot;9204911a1d27459b9d4cff24a71bbd1f&quot;}"',
            $html,
        );
    }

    private function context(?string $token = null): RenderContext
    {
        return new RenderContext(
            'pl',
            new MarkdownRenderer(),
            new PartialRenderer(new PartialRegistry($this->project->path()), new OutputBuffer()),
            cloudflareBeaconToken: $token,
        );
    }
}
