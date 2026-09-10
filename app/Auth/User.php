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
        private string $firstName = '',
        private string $lastName = '',
        private string $publicId = '',
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


    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function displayName(): string
    {
        $name = trim("{$this->firstName} {$this->lastName}");

        return $name !== '' ? $name : $this->email;
    }

    public function initials(): string
    {
        if ($this->firstName !== '' && $this->lastName !== '') {
            return mb_strtoupper(mb_substr($this->firstName, 0, 1) . mb_substr($this->lastName, 0, 1));
        }

        return mb_strtoupper(mb_substr($this->email, 0, 1));
    }

    public function publicId(): string
    {
        return $this->publicId;
    }
}
