<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

final readonly class ReleaseCheckResult
{
    public function __construct(
        public string $name,
        public bool $passed,
        public string $message,
    ) {}
}
