<?php

declare(strict_types=1);

namespace FlatFileCms\Seo;

use FlatFileCms\Http\PublicHttpUrl;
use InvalidArgumentException;
use Uri\Rfc3986\Uri;

final readonly class CanonicalReference
{
    private function __construct(
        private string $value,
        private bool $siteRelative,
    ) {}

    public static function fromString(string $value): self
    {
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            $uri = Uri::parse($value);
            if (
                $uri === null
                || $uri->getScheme() !== null
                || $uri->getHost() !== null
                || $uri->getRawPath() === ''
                || $uri->getFragment() !== null
            ) {
                throw new InvalidArgumentException('Invalid canonical site path.');
            }

            return new self($uri->toRawString(), true);
        }

        $url = PublicHttpUrl::fromString($value, allowFragment: false);

        return new self($url->value(), false);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function absolute(PublicHttpUrl $baseUrl): string
    {
        return $this->siteRelative ? $baseUrl->resolve($this->value) : $this->value;
    }
}
