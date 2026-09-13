<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use RuntimeException;

final class BlockValidationException extends RuntimeException
{
    /** @var non-empty-list<ValidationError> */
    public private(set) array $errors {
        get => $this->errors;
    }

    /** @param non-empty-list<ValidationError> $errors */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Block data failed schema validation.');
    }

    /** @return non-empty-list<ValidationError> */
    #[\Deprecated(message: 'Use the native $errors property instead.', since: 'next')]
    public function errors(): array
    {
        return $this->errors;
    }
}
