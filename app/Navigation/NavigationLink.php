<?php

declare(strict_types=1);

namespace FlatFileCms\Navigation;

use FlatFileCms\Http\PublicHttpUrl;
use InvalidArgumentException;
use Uri\Rfc3986\Uri;

final readonly class NavigationLink
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            $uri = Uri::parse($value);
            if ($uri === null || $uri->getScheme() !== null || $uri->getHost() !== null) {
                throw new InvalidArgumentException('Navigation URL is invalid.');
            }

            return new self($value);
        }

        $uri = Uri::parse($value);
        $scheme = $uri?->getScheme();
        if (\in_array($scheme, ['http', 'https'], true)) {
            return new self(PublicHttpUrl::fromString($value)->value());
        }
        if ($uri === null || $uri->getFragment() !== null) {
            throw new InvalidArgumentException('Navigation URL is invalid.');
        }

        $target = rawurldecode($uri->getRawPath());
        $valid = match ($scheme) {
            'mailto' => filter_var($target, FILTER_VALIDATE_EMAIL) !== false,
            'tel' => preg_match('/^\+?[0-9(). -]*[0-9][0-9(). -]*$/D', $target) === 1,
            default => false,
        };
        if (!$valid) {
            throw new InvalidArgumentException('Navigation URL is invalid.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
