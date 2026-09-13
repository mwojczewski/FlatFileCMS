<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

final class ArraySessionStore implements SessionStore
{
    /** @var array<string, mixed> */
    private array $values = [];

    #[\Override]
    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    #[\Override]
    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    #[\Override]
    public function regenerate(): void {}

    #[\Override]
    public function invalidate(): void
    {
        $this->values = [];
    }
}
