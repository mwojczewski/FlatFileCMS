<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\ApiDocs;

use FlatFileCms\ApiDocs\ApiDocumentationController;
use FlatFileCms\ApiDocs\OpenApiDocument;
use FlatFileCms\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiDocumentationController::class)]
final class ApiDocumentationControllerTest extends TestCase
{
    private ApiDocumentationController $controller;

    protected function setUp(): void
    {
        $this->controller = new ApiDocumentationController(
            new OpenApiDocument(\dirname(__DIR__, 3) . '/docs/openapi.yaml'),
        );
    }

    public function testItServesOpenApiJson(): void
    {
        $response = $this->controller->specification(new Request('GET', '/api/openapi.json'));
        $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->status());
        self::assertIsArray($data);
        self::assertSame('3.1.0', $data['openapi']);
        self::assertStringStartsWith('application/vnd.oai.openapi+json', $response->headers()['Content-Type']);
        self::assertArrayHasKey('ETag', $response->headers());
    }

    public function testItSupportsConditionalSpecificationRequests(): void
    {
        $first = $this->controller->specification(new Request('GET', '/api/openapi.json'));
        $second = $this->controller->specification(new Request(
            'GET',
            '/api/openapi.json',
            headers: ['if-none-match' => $first->headers()['ETag']],
        ));

        self::assertSame(304, $second->status());
        self::assertSame('', $second->body());
    }

    public function testItServesScalarDocumentationWithSecurityHeaders(): void
    {
        $response = $this->controller->documentation(new Request('GET', '/api/docs'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('/api/openapi.json', $response->body());
        self::assertStringContainsString('@scalar/api-reference', $response->body());
        self::assertArrayHasKey('Content-Security-Policy', $response->headers());
    }
}
