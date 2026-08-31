<?php

declare(strict_types=1);

namespace FlatFileCms\Redirects;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;

final readonly class RedirectDocument
{
    /** @param list<RedirectRule> $rules */
    public function __construct(
        private array $rules,
        private FileRevision $revision,
    ) {}

    /** @return list<RedirectRule> */
    public function rules(): array
    {
        return $this->rules;
    }

    public function revision(): FileRevision
    {
        return $this->revision;
    }
}
