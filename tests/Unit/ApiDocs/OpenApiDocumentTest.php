<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\ApiDocs;

use FlatFileCms\ApiDocs\OpenApiDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenApiDocument::class)]
final class OpenApiDocumentTest extends TestCase
{
    public function testItLoadsThePublicApiContract(): void
    {
        $document = new OpenApiDocument(\dirname(__DIR__, 3) . '/docs/openapi.yaml');
        $data = $document->data();

        $info = $data['info'] ?? null;
        $paths = $data['paths'] ?? null;

        self::assertIsArray($info);
        self::assertIsArray($paths);

        self::assertSame('3.1.0', $data['openapi']);
        self::assertSame('FlatfileCMS Public API', $info['title']);
        self::assertSame([
            '/api/v1/health',
            '/api/v1/pages',
            '/api/v1/pages/{path}',
            '/api/v1/navigation',
            '/api/v1/config',
            '/api/v1/collections/{path}',
        ], array_keys($paths));
    }
}
