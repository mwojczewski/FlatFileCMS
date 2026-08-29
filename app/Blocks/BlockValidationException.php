<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use RuntimeException;

final class BlockValidationException extends RuntimeException
{
    /** @param non-empty-list<ValidationError> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Block data failed schema validation.');
    }

    /** @return non-empty-list<ValidationError> */
    public function errors(): array
    {
        return $this->errors;
    }
}
