<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public private(set) int $status {
        get => $this->status;
    }

    public private(set) string $errorCode {
        get => $this->errorCode;
    }

    public function __construct(
        int $status,
        string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        $this->status = $status;
        $this->errorCode = $errorCode;
        parent::__construct($message, previous: $previous);
    }

    #[\Deprecated(message: 'Use the native $status property instead.', since: 'next')]
    public function status(): int
    {
        return $this->status;
    }

    #[\Deprecated(message: 'Use the native $errorCode property instead.', since: 'next')]
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
