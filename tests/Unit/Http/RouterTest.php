<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Http;

use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Http\Route;
use FlatFileCms\Http\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Router::class)]
#[CoversClass(Route::class)]
final class RouterTest extends TestCase
{
    public function testItDispatchesAnExactRoute(): void
    {
        $router = new Router();
        $router->get('/health', static fn(): Response => Response::json(['status' => 'ok']), 'health');

        $response = $router->dispatch(new Request('GET', '/health'));

        self::assertSame(200, $response->status());
        self::assertSame('{"status":"ok"}', $response->body());
    }

    public function testItSuppliesDecodedRouteParameters(): void
    {
        $router = new Router();
        $router->get(
            '/hello/{name}',
            static fn(Request $request): Response => Response::html((string) $request->attribute('name')),
            'hello',
        );

        $response = $router->dispatch(new Request('GET', '/hello/Micha%C5%82'));

        self::assertSame('Michał', $response->body());
    }

    public function testItReturnsAHeadResponseWithoutBody(): void
    {
        $router = new Router();
        $router->get('/health', static fn(): Response => Response::html('healthy'), 'health');

        $response = $router->dispatch(new Request('HEAD', '/health'));

        self::assertSame(200, $response->status());
        self::assertSame('', $response->body());
    }

    public function testItDistinguishesNotFoundAndMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/health', static fn(): Response => Response::html('healthy'), 'health');

        try {
            $router->dispatch(new Request('POST', '/health'));
            self::fail('Expected method-not-allowed exception.');
        } catch (HttpException $exception) {
            self::assertSame(405, $exception->status());
            self::assertSame('METHOD_NOT_ALLOWED', $exception->errorCode());
        }

        try {
            $router->dispatch(new Request('GET', '/missing'));
            self::fail('Expected not-found exception.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->status());
            self::assertSame('ROUTE_NOT_FOUND', $exception->errorCode());
        }
    }
}
