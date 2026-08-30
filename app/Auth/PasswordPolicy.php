<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use InvalidArgumentException;

final readonly class PasswordPolicy
{
    public function validate(string $password): void
    {
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters.');
        }
        if (preg_match('/[a-z]/', $password) !== 1) {
            throw new InvalidArgumentException('Password must contain a lowercase letter.');
        }
        if (preg_match('/[A-Z]/', $password) !== 1) {
            throw new InvalidArgumentException('Password must contain an uppercase letter.');
        }
        if (preg_match('/[0-9]/', $password) !== 1) {
            throw new InvalidArgumentException('Password must contain a digit.');
        }
        if (preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
            throw new InvalidArgumentException('Password must contain a special character.');
        }
    }
}
