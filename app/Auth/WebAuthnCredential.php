<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

final readonly class WebAuthnCredential
{
    /** @param list<string> $transports */
    public function __construct(
        private int $id,
        private int $userId,
        private string $name,
        private string $credentialId,
        private string $publicKey,
        private int $signatureCounter,
        private array $transports,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function credentialId(): string
    {
        return $this->credentialId;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function signatureCounter(): int
    {
        return $this->signatureCounter;
    }

    /** @return list<string> */
    public function transports(): array
    {
        return $this->transports;
    }
}
