<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

final readonly class ApiErrorResponder
{
    public function respond(
        int $status,
        string $code,
        string $message,
        ?string $debugDetails = null,
    ): Response {
        $error = ['code' => $code, 'message' => $message];
        if ($debugDetails !== null) {
            $error['debug'] = $debugDetails;
        }

        return Response::json(['error' => $error], $status);
    }
}
