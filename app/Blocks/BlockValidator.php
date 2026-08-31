<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use FlatFileCms\Blocks\Field\FieldContext;
use FlatFileCms\Blocks\Field\FieldTypeRegistry;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;

final readonly class BlockValidator
{
    public function __construct(private FieldTypeRegistry $fieldTypes) {}

    /** @param array<string, mixed> $data */
    public function validate(
        BlockDefinition $definition,
        array $data,
        LanguageConfig $languages,
        ?PageIdentity $pageIdentity = null,
    ): NormalizedBlockData {
        $errors = [];
        $context = new FieldContext($languages, $pageIdentity);
        $normalized = $this->validateFields($definition->fields(), $data, 'data', $context, $errors);

        if ($errors !== []) {
            /** @var non-empty-list<ValidationError> $errors */
            throw new BlockValidationException($errors);
        }

        return new NormalizedBlockData($normalized);
    }

    /** @return array<string, mixed> */
    public function localize(
        BlockDefinition $definition,
        NormalizedBlockData $data,
        string $locale,
        LanguageConfig $languages,
        ?PageIdentity $pageIdentity = null,
    ): array {
        $context = new FieldContext($languages, $pageIdentity);

        return $this->localizeFields($definition->fields(), $data->values(), $locale, $context);
    }

    /**
     * @param array<string, FieldDefinition> $definitions
     * @param array<string, mixed> $values
     * @param list<ValidationError> $errors
     * @return array<string, mixed>
     */
    private function validateFields(
        array $definitions,
        array $values,
        string $path,
        FieldContext $context,
        array &$errors,
    ): array {
        foreach ($values as $name => $value) {
            if (!isset($definitions[$name])) {
                $errors[] = new ValidationError($path . '.' . $name, 'UNKNOWN_FIELD', 'Field is not defined.');
            }
        }

        $normalized = [];
        foreach ($definitions as $name => $definition) {
            $fieldPath = $path . '.' . $name;
            if (!\array_key_exists($name, $values)) {
                if ($definition->required()) {
                    $errors[] = new ValidationError($fieldPath, 'REQUIRED', 'Field is required.');
                }

                continue;
            }

            $value = $values[$name];
            if ($definition->translatable()) {
                $localized = $this->localizedMapping($value, $fieldPath, $context, $errors);
                if ($localized === null) {
                    continue;
                }

                $normalizedLocales = [];
                foreach ($context->languages()->codes() as $locale) {
                    if (!\array_key_exists($locale, $localized)) {
                        if ($definition->required() && $locale === $context->languages()->default()) {
                            $errors[] = new ValidationError(
                                $fieldPath . '.' . $locale,
                                'REQUIRED_TRANSLATION',
                                'Default translation is required.',
                            );
                        }

                        continue;
                    }

                    $localizedValue = $localized[$locale];
                    if ($definition->required() && $this->isEmpty($localizedValue)) {
                        $errors[] = new ValidationError(
                            $fieldPath . '.' . $locale,
                            'REQUIRED_TRANSLATION',
                            'Translation cannot be empty.',
                        );

                        continue;
                    }

                    $normalizedValue = $this->normalizeValue(
                        $definition,
                        $localizedValue,
                        $fieldPath . '.' . $locale,
                        $context,
                        $errors,
                    );
                    if ($normalizedValue['valid']) {
                        $normalizedLocales[$locale] = $normalizedValue['value'];
                    }
                }

                if ($normalizedLocales !== []) {
                    $normalized[$name] = $normalizedLocales;
                }

                continue;
            }

            if ($definition->required() && $this->isEmpty($value)) {
                $errors[] = new ValidationError($fieldPath, 'REQUIRED', 'Field cannot be empty.');

                continue;
            }

            $normalizedValue = $this->normalizeValue($definition, $value, $fieldPath, $context, $errors);
            if ($normalizedValue['valid']) {
                $normalized[$name] = $normalizedValue['value'];
            }
        }

        return $normalized;
    }

    /**
     * @param list<ValidationError> $errors
     * @return array{valid: bool, value: mixed}
     */
    private function normalizeValue(
        FieldDefinition $definition,
        mixed $value,
        string $path,
        FieldContext $context,
        array &$errors,
    ): array {
        try {
            $fieldType = $this->fieldTypes->get($definition->type());
            $normalized = $fieldType->normalize($value, $definition, $context);
            if ($definition->type() !== 'repeater') {
                return ['valid' => true, 'value' => $normalized];
            }
            if (!\is_array($normalized) || !array_is_list($normalized)) {
                throw new FieldValueException('INVALID_TYPE', 'Repeater must normalize to a list.');
            }

            $items = [];
            foreach ($normalized as $index => $item) {
                if (!\is_array($item) || ($item !== [] && array_is_list($item))) {
                    $errors[] = new ValidationError(
                        $path . '.' . $index,
                        'INVALID_TYPE',
                        'Repeater item must be a mapping.',
                    );

                    continue;
                }

                $mapping = [];
                foreach ($item as $key => $nestedValue) {
                    if (!\is_string($key)) {
                        $errors[] = new ValidationError(
                            $path . '.' . $index,
                            'INVALID_TYPE',
                            'Repeater item keys must be strings.',
                        );

                        continue 2;
                    }

                    $mapping[$key] = $nestedValue;
                }
                $items[] = $this->validateFields(
                    $definition->fields(),
                    $mapping,
                    $path . '.' . $index,
                    $context,
                    $errors,
                );
            }

            return ['valid' => true, 'value' => $items];
        } catch (FieldValueException $exception) {
            $errors[] = new ValidationError($path, $exception->validationCode(), $exception->getMessage());

            return ['valid' => false, 'value' => null];
        } catch (InvalidBlockDefinitionException $exception) {
            $errors[] = new ValidationError($path, 'INVALID_SCHEMA', $exception->getMessage());

            return ['valid' => false, 'value' => null];
        }
    }

    /**
     * @param list<ValidationError> $errors
     * @return array<string, mixed>|null
     */
    private function localizedMapping(
        mixed $value,
        string $path,
        FieldContext $context,
        array &$errors,
    ): ?array {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            $errors[] = new ValidationError($path, 'INVALID_TRANSLATIONS', 'Value must be a locale mapping.');

            return null;
        }

        $localized = [];
        foreach ($value as $locale => $localizedValue) {
            if (!\is_string($locale) || !$context->languages()->has($locale)) {
                $errors[] = new ValidationError(
                    $path . '.' . (\is_string($locale) ? $locale : '?'),
                    'UNKNOWN_LOCALE',
                    'Translation uses a language that is not enabled.',
                );

                continue;
            }

            $localized[$locale] = $localizedValue;
        }

        return $localized;
    }

    /**
     * @param array<string, FieldDefinition> $definitions
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function localizeFields(
        array $definitions,
        array $values,
        string $locale,
        FieldContext $context,
    ): array {
        $localized = [];
        foreach ($definitions as $name => $definition) {
            if (!\array_key_exists($name, $values)) {
                continue;
            }

            $value = $values[$name];
            if ($definition->translatable()) {
                if (!\is_array($value)) {
                    continue;
                }

                $value = $value[$locale] ?? $value[$context->languages()->default()] ?? null;
                if ($value === null) {
                    continue;
                }
            }

            if ($definition->type() === 'repeater' && \is_array($value)) {
                $items = [];
                foreach ($value as $item) {
                    if (\is_array($item)) {
                        $mapping = [];
                        foreach ($item as $key => $nestedValue) {
                            if (\is_string($key)) {
                                $mapping[$key] = $nestedValue;
                            }
                        }
                        $items[] = $this->localizeFields($definition->fields(), $mapping, $locale, $context);
                    }
                }
                $value = $items;
            }

            $localized[$name] = $this->fieldTypes
                ->get($definition->type())
                ->localize($value, $locale, $definition, $context);
        }

        return $localized;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
