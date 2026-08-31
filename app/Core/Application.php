<?php

declare(strict_types=1);

namespace FlatFileCms\Core;

use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Http\Router;
use FlatFileCms\Http\TrustedProxyResolver;
use Throwable;

final readonly class Application
{
    public function __construct(
        private Router $router,
        private ErrorHandler $errorHandler,
        private ?TrustedProxyResolver $trustedProxies = null,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            if ($this->trustedProxies !== null) {
                $request = $request->withClientIp($this->trustedProxies->resolve(
                    $request->clientIp(),
                    $request->header('x-forwarded-for'),
                ));
            }

            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            return $this->errorHandler->render($request, $exception);
        }
    }
}
