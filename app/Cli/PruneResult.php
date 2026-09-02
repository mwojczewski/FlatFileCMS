<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

final readonly class PruneResult
{
    public function __construct(
        public int $files,
        public int $bytes,
    ) {}

    public function plus(self $other): self
    {
        return new self($this->files + $other->files, $this->bytes + $other->bytes);
    }
}
