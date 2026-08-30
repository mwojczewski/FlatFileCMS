<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks\Field;

use DateTimeImmutable;
use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Blocks\FieldValueException;

final readonly class FormattedStringFieldType implements FieldType
{
    public function __construct(private string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function validateDefinition(FieldDefinition $definition): void {}

    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): string
    {
        if (!\is_string($value)) {
            throw new FieldValueException('INVALID_TYPE', 'Value must be a string.');
        }

        $value = trim($value);
        $valid = match ($this->name) {
            'url' => $this->validUrl($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'date' => $this->validDate($value, 'Y-m-d'),
            'datetime' => $this->validDateTime($value),
            'color' => preg_match('/^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/D', $value) === 1,
            default => false,
        };

        if (!$valid) {
            throw new FieldValueException('INVALID_FORMAT', \sprintf('Value is not a valid %s.', $this->name));
        }

        return $this->name === 'color' ? strtolower($value) : $value;
    }

    public function localize(
        mixed $value,
        string $locale,
        FieldDefinition $definition,
        FieldContext $context,
    ): mixed {
        return $value;
    }

    private function validUrl(string $value): bool
    {
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && \in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function validDate(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private function validDateTime(string $value): bool
    {
        foreach (['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s', DATE_RFC3339] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }
}
