<?php

declare(strict_types=1);

namespace FlatFileCms\Domain\Localization;

final readonly class LocalizedDataResolver
{
    public function resolve(mixed $value, string $locale, LanguageConfig $languages): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isLocalizedMap($value, $languages)) {
            $localized = $value[$locale] ?? $value[$languages->default()] ?? null;

            return $this->resolve($localized, $locale, $languages);
        }

        $resolved = [];
        foreach ($value as $key => $item) {
            $resolved[$key] = $this->resolve($item, $locale, $languages);
        }

        return $resolved;
    }

    /** @param array<mixed> $value */
    private function isLocalizedMap(array $value, LanguageConfig $languages): bool
    {
        if ($value === [] || array_is_list($value)) {
            return false;
        }

        $hasLocale = false;
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !$languages->has($key)) {
                return false;
            }

            $hasLocale = true;
        }

        return $hasLocale;
    }
}
