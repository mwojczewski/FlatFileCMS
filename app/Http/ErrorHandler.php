<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use Throwable;

final readonly class ErrorHandler
{
    public function __construct(private bool $debug = false) {}

    public function render(Request $request, Throwable $exception): Response
    {
        $status = $exception instanceof HttpException ? $exception->status() : 500;
        $code = $exception instanceof HttpException ? $exception->errorCode() : 'INTERNAL_SERVER_ERROR';
        $publicMessage = $exception instanceof HttpException ? $exception->getMessage() : 'Internal server error';

        if (!$exception instanceof HttpException) {
            error_log(sprintf(
                '[FlatFile CMS] %s: %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));
        }

        if (str_starts_with($request->path(), '/api/')) {
            $error = ['code' => $code, 'message' => $publicMessage];
            if ($this->debug) {
                $error['debug'] = $exception->getMessage();
            }

            return Response::json(['error' => $error], $status);
        }

        $title = $status === 404 ? 'Nie znaleziono strony' : 'Wystąpił błąd';
        $detail = $this->debug ? $exception->getMessage() : $publicMessage;

        return Response::html(
            '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>'
            . self::escape($title) . '</title></head><body><main><h1>'
            . self::escape($title) . '</h1><p>' . self::escape($detail) . '</p></main></body></html>',
            $status,
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
