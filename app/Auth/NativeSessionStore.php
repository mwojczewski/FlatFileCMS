<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use RuntimeException;

final class NativeSessionStore implements SessionStore
{
    public function __construct(
        string $savePath,
        string $name,
        int $lifetime,
        bool $secure,
        string $sameSite,
    ) {
        if (!in_array($sameSite, ['Lax', 'Strict'], true)) {
            throw new RuntimeException('Session SameSite must be Lax or Strict.');
        }
        if (session_status() === PHP_SESSION_NONE) {
            if (!is_dir($savePath) && !mkdir($savePath, 0o700, true) && !is_dir($savePath)) {
                throw new RuntimeException('Unable to create session directory.');
            }
            session_save_path($savePath);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_name($name);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ]);
            if (!session_start()) {
                throw new RuntimeException('Unable to start administrator session.');
            }
        }
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to regenerate session identifier.');
        }
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $parameters = session_get_cookie_params();
            $sessionName = session_name();
            if (!is_string($sessionName)) {
                throw new RuntimeException('Unable to read session name.');
            }
            setcookie($sessionName, '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'],
            ]);
            session_destroy();
        }
    }
}
