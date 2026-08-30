<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

final readonly class User
{
    public function __construct(
        private int $id,
        private string $email,
        private string $passwordHash,
        private Role $role,
        private bool $enabled,
        private string $webAuthnUserHandle,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function webAuthnUserHandle(): string
    {
        return $this->webAuthnUserHandle;
    }
}
