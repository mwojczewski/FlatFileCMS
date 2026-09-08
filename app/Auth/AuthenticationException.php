<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use RuntimeException;
use Throwable;

final class AuthenticationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $reason = 'authentication_failed',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
