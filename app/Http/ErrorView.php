<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

final readonly class ErrorView
{
    public function __construct(
        private int $status,
        private string $description,
        private string $homepageUrl,
    ) {}

    public function status(): int
    {
        return $this->status;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function homepageUrl(): string
    {
        return $this->homepageUrl;
    }
}
