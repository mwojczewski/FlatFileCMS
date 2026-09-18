<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Http;

use FlatFileCms\Http\ApiErrorResponder;
use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\WebErrorRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ErrorHandler::class)]
final class ErrorHandlerTest extends TestCase
{
    public function testErrorRenderersRemainLazyUntilTheirResponseFormatIsNeeded(): void
    {
        $handler = new ErrorHandler(
            apiErrors: static fn(): ApiErrorResponder => new ApiErrorResponder(),
            webErrors: static function (): WebErrorRenderer {
                throw new RuntimeException('The web renderer must stay lazy for API errors.');
            },
        );

        $response = $handler->render(
            new Request('GET', '/api/v1/missing'),
            new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found'),
        );

        self::assertSame(404, $response->status());
    }

    public function testItUsesTheApiErrorEnvelope(): void
    {
        $handler = new ErrorHandler();

        $response = $handler->render(
            new Request('GET', '/api/v1/missing'),
            new HttpException(404, 'PAGE_NOT_FOUND', 'Page not found'),
        );

        self::assertSame(404, $response->status());
        self::assertSame(
            '{"error":{"code":"PAGE_NOT_FOUND","message":"Page not found"}}',
            $response->body(),
        );
    }

    public function testProductionResponseDoesNotExposeInternalExceptionDetails(): void
    {
        $handler = new ErrorHandler(debug: false);

        $response = $handler->render(
            new Request('GET', '/api/v1/pages'),
            new RuntimeException('Secret filesystem path'),
        );

        self::assertStringNotContainsString('Secret filesystem path', $response->body());
        self::assertStringContainsString('Internal server error', $response->body());
    }

    public function testAdministratorHtmlErrorsAreNeverCachedOrFramed(): void
    {
        $handler = new ErrorHandler();

        $response = $handler->render(
            new Request('GET', '/admin/pages/edit'),
            new HttpException(409, 'PAGE_REVISION_CONFLICT', 'Page changed in another session.'),
        );

        self::assertSame('no-store', $response->headers()['Cache-Control'] ?? null);
        self::assertSame('no-cache', $response->headers()['Pragma'] ?? null);
        self::assertSame('DENY', $response->headers()['X-Frame-Options'] ?? null);
    }

    public function testUnauthenticatedAdministratorHtmlRequestRedirectsToLogin(): void
    {
        $handler = new ErrorHandler();
        $response = $handler->render(
            new Request('GET', '/admin/pages'),
            new HttpException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required.'),
        );

        self::assertSame(302, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location'] ?? null);
        self::assertSame('no-store', $response->headers()['Cache-Control'] ?? null);
    }

    public function testUnauthenticatedJsonRequestKeepsErrorEnvelope(): void
    {
        $handler = new ErrorHandler();
        $response = $handler->render(
            new Request('GET', '/admin/media/picker', headers: ['accept' => 'application/json']),
            new HttpException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required.'),
        );

        self::assertSame(401, $response->status());
        self::assertStringContainsString('AUTHENTICATION_REQUIRED', $response->body());
    }
}
