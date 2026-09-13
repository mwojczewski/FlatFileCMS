<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

final readonly class PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $password): string
    {
        $algorithm = \in_array('argon2id', password_algos(), true) ? 'argon2id' : PASSWORD_DEFAULT;

        return password_hash($password, $algorithm);
    }

    public function verify(
        #[\SensitiveParameter]
        string $password,
        #[\SensitiveParameter]
        string $hash,
    ): bool {
        return password_verify($password, $hash);
    }
}
