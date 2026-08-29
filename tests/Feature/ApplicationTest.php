<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Feature;

use FlatFileCms\Core\Application;
use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $router = new Router();
        $registerRoutes = require dirname(__DIR__, 2) . '/config/routes.php';
        if (!is_callable($registerRoutes)) {
            throw new RuntimeException('Route configuration must return a callable.');
        }

        $registerRoutes($router);
        $this->application = new Application($router, new ErrorHandler(false));
    }

    public function testHealthEndpointIsOperational(): void
    {
        $response = $this->application->handle(new Request('GET', '/api/v1/health'));

        self::assertSame(200, $response->status());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame(
            ['status' => 'ok', 'application' => 'FlatFile CMS', 'stage' => 1],
            json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testMissingApiRouteUsesJsonErrorContract(): void
    {
        $response = $this->application->handle(new Request('GET', '/api/v1/missing'));

        self::assertSame(404, $response->status());
        self::assertStringContainsString('ROUTE_NOT_FOUND', $response->body());
    }
}
