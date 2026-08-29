<?php

declare(strict_types=1);

namespace FlatFileCms\Core;

use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;
use FlatFileCms\Http\Router;
use Throwable;

final readonly class Application
{
    public function __construct(
        private Router $router,
        private ErrorHandler $errorHandler,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            return $this->errorHandler->render($request, $exception);
        }
    }
}
