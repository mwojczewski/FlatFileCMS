<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

final readonly class CsrfTokenManager
{
    private const string SESSION_KEY = 'csrf_token';

    public function __construct(private SessionStore $session) {}

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function validate(mixed $token): void
    {
        if (!is_string($token) || !hash_equals($this->token(), $token)) {
            throw new AuthenticationException('Invalid CSRF token.');
        }
    }
}
