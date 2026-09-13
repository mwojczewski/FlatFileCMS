<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

use InvalidArgumentException;
use Uri\WhatWg\Url;

final readonly class PublicHttpUrl
{
    private function __construct(
        private string $value,
        private Url $url,
    ) {}

    public static function fromString(
        string $value,
        bool $allowCredentials = false,
        bool $allowFragment = true,
    ): self {
        $errors = [];
        $url = Url::parse($value, errors: $errors);

        if (
            $url === null
            || $errors !== []
            || !\in_array($url->getScheme(), ['http', 'https'], true)
            || $url->getAsciiHost() === null
            || (!$allowCredentials && ($url->getUsername() !== null || $url->getPassword() !== null))
            || (!$allowFragment && $url->getFragment() !== null)
        ) {
            throw new InvalidArgumentException('Expected an absolute HTTP(S) URL.');
        }

        return new self($value, $url);
    }

    public static function isValid(
        string $value,
        bool $allowCredentials = false,
        bool $allowFragment = true,
    ): bool {
        try {
            self::fromString($value, $allowCredentials, $allowFragment);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function resolve(string $reference): string
    {
        return $this->url->resolve($reference)->toAsciiString();
    }
}
