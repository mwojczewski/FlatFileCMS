<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use RuntimeException;

final class FieldValueException extends RuntimeException
{
    public private(set) string $validationCode {
        get => $this->validationCode;
    }

    public function __construct(
        string $validationCode,
        string $message,
    ) {
        $this->validationCode = $validationCode;
        parent::__construct($message);
    }

    #[\Deprecated(message: 'Use the native $validationCode property instead.', since: 'next')]
    public function validationCode(): string
    {
        return $this->validationCode;
    }
}
