<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Blocks\BlockDefinition;
use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Domain\Localization\LanguageConfig;
use InvalidArgumentException;

final readonly class BlockFormDataMapper
{
    /** @return array<string, mixed> */
    public function map(BlockDefinition $definition, mixed $submitted, LanguageConfig $languages): array
    {
        return $this->fields($definition->fields(), $this->mapping($submitted), $languages);
    }

    /**
     * @param array<string, FieldDefinition> $definitions
     * @param array<string, mixed> $submitted
     * @return array<string, mixed>
     */
    private function fields(array $definitions, array $submitted, LanguageConfig $languages): array
    {
        $result = [];
        foreach ($definitions as $name => $definition) {
            if (!\array_key_exists($name, $submitted)) {
                continue;
            }
            $value = $submitted[$name];
            if ($definition->translatable()) {
                $localized = $this->mapping($value);
                $translations = [];
                foreach ($languages->codes() as $locale) {
                    if (!\array_key_exists($locale, $localized)) {
                        continue;
                    }
                    $normalized = $this->value($definition, $localized[$locale], $languages);
                    if (!$this->empty($normalized) || $definition->required()) {
                        $translations[$locale] = $normalized;
                    }
                }
                if ($translations !== [] || $definition->required()) {
                    $result[$name] = $translations;
                }

                continue;
            }

            $normalized = $this->value($definition, $value, $languages);
            if (!$this->empty($normalized) || $definition->required() || $definition->type() === 'boolean') {
                $result[$name] = $normalized;
            }
        }

        return $result;
    }

    private function value(FieldDefinition $definition, mixed $value, LanguageConfig $languages): mixed
    {
        if ($definition->type() === 'repeater') {
            if (!\is_array($value)) {
                return $value;
            }
            $items = [];
            foreach (array_values($value) as $item) {
                $mapped = $this->fields($definition->fields(), $this->mapping($item), $languages);
                if ($mapped !== [] || $definition->required()) {
                    $items[] = $mapped;
                }
            }

            return $items;
        }
        if (\in_array($definition->type(), ['image', 'file'], true)) {
            $media = $this->mapping($value);
            $src = $media['src'] ?? '';
            if (!\is_string($src)) {
                throw new InvalidArgumentException('Media source must be a string.');
            }
            $result = ['src' => trim($src)];
            if ($definition->type() === 'image' && isset($media['alt'])) {
                $alt = [];
                foreach ($this->mapping($media['alt']) as $locale => $text) {
                    if ($languages->has($locale) && \is_string($text)) {
                        $alt[$locale] = trim($text);
                    }
                }
                if ($alt !== []) {
                    $result['alt'] = $alt;
                }
            }

            return $result;
        }
        if ($definition->type() === 'multiselect') {
            if (!\is_array($value)) {
                return [];
            }

            return array_values(array_filter($value, static fn(mixed $item): bool => \is_string($item)));
        }

        return \is_string($value) ? trim($value) : $value;
    }

    /** @return array<string, mixed> */
    private function mapping(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException('Block form data must be a mapping.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Block form keys must be strings.');
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private function empty(mixed $value): bool
    {
        if (\is_array($value) && isset($value['src'])) {
            return $value['src'] === '';
        }

        return $value === '' || $value === [] || $value === null;
    }
}
