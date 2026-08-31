<?php

declare(strict_types=1);

namespace FlatFileCms\Redirects;

final readonly class RedirectRule
{
    public function __construct(
        private string $id,
        private string $source,
        private string $target,
        private int $status,
        private bool $enabled,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function target(): string
    {
        return $this->target;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** @return array{id: string, source: string, target: string, status: int, enabled: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'target' => $this->target,
            'status' => $this->status,
            'enabled' => $this->enabled,
        ];
    }
}
