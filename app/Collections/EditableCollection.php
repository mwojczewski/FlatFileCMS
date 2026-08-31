<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class EditableCollection
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private PageIdentity $identity,
        private array $data,
        private FileRevision $revision,
    ) {}

    public function identity(): PageIdentity
    {
        return $this->identity;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function revision(): FileRevision
    {
        return $this->revision;
    }
}
