<?php

declare(strict_types=1);

namespace FlatFileCms\Navigation;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class NavigationDocument
{
    /** @param array<string, list<array<string, mixed>>> $menus */
    public function __construct(
        private array $menus,
        private FileRevision $revision,
        private int $modifiedAt,
    ) {}

    /** @return array<string, list<array<string, mixed>>> */
    public function menus(): array
    {
        return $this->menus;
    }

    public function revision(): FileRevision
    {
        return $this->revision;
    }

    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
}
