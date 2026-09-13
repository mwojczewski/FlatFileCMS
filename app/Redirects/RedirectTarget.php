<?php

declare(strict_types=1);

namespace FlatFileCms\Redirects;

use FlatFileCms\Http\PublicHttpUrl;
use InvalidArgumentException;
use Uri\Rfc3986\Uri;

final readonly class RedirectTarget
{
    private function __construct(
        private string $value,
        private ?string $sitePath,
    ) {}

    public static function fromString(string $target): self
    {
        if ($target === '' || str_contains($target, "\0") || preg_match('/[\x01-\x1F\x7F\\\\]/', $target) === 1) {
            throw new InvalidArgumentException('Redirect target contains unsafe characters.');
        }

        if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            $uri = Uri::parse($target);
            $path = $uri?->getRawPath();
            if (
                $uri === null
                || $uri->getScheme() !== null
                || $uri->getHost() !== null
                || !\is_string($path)
                || preg_match('#(?:^|/)(?:(?:\.|%2e){1,2})(?:/|$)#i', $path) === 1
            ) {
                throw new InvalidArgumentException('Redirect target path is invalid.');
            }

            return new self($target, $path === '' ? '/' : $path);
        }

        return new self(PublicHttpUrl::fromString($target)->value(), null);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function sitePath(): ?string
    {
        return $this->sitePath;
    }
}
