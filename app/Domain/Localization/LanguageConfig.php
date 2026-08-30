<?php

declare(strict_types=1);

namespace FlatFileCms\Domain\Localization;

use InvalidArgumentException;

final readonly class LanguageConfig
{
    /** @param non-empty-array<string, string> $languages */
    public function __construct(
        private string $default,
        private array $languages,
    ) {
        if (!isset($this->languages[$this->default])) {
            throw new InvalidArgumentException('Default language must be enabled.');
        }

        foreach ($this->languages as $code => $name) {
            if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/D', $code) !== 1 || $name === '') {
                throw new InvalidArgumentException('Language codes or names are invalid.');
            }
        }
    }

    public function default(): string
    {
        return $this->default;
    }

    /** @return non-empty-list<string> */
    public function codes(): array
    {
        /** @var non-empty-list<string> */
        return array_keys($this->languages);
    }

    /** @return non-empty-array<string, string> */
    public function languages(): array
    {
        return $this->languages;
    }

    public function has(string $locale): bool
    {
        return isset($this->languages[$locale]);
    }

    public function isMultilingual(): bool
    {
        return \count($this->languages) > 1;
    }
}
