<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use RuntimeException;

final class FieldValueException extends RuntimeException
{
    public function __construct(
        private readonly string $validationCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function validationCode(): string
    {
        return $this->validationCode;
    }
}
