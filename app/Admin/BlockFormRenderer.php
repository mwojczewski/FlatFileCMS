<?php

declare(strict_types=1);

namespace FlatFileCms\Admin;

use FlatFileCms\Blocks\BlockDefinition;
use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Domain\Localization\LanguageConfig;

final readonly class BlockFormRenderer
{
    /** @param array<string, mixed> $data */
    public function render(BlockDefinition $definition, LanguageConfig $languages, array $data): string
    {
        return '<div class="generated-fields">'
            . $this->fields($definition->fields(), $languages, $data, 'data', 0)
            . '</div>';
    }

    /**
     * @param array<string, FieldDefinition> $definitions
     * @param array<string, mixed> $values
     */
    private function fields(
        array $definitions,
        LanguageConfig $languages,
        array $values,
        string $prefix,
        int $depth,
    ): string {
        $html = '';
        foreach ($definitions as $name => $definition) {
            $value = $values[$name] ?? null;
            $html .= $this->field($definition, $languages, $value, $prefix . '[' . $name . ']', $depth);
        }

        return $html;
    }

    private function field(
        FieldDefinition $definition,
        LanguageConfig $languages,
        mixed $value,
        string $name,
        int $depth,
    ): string {
        $label = $this->uiText($definition, 'label', $languages->default(), $definition->name());
        $help = $this->uiText($definition, 'help', $languages->default(), '');
        $required = $definition->required() ? ' <span class="required">*</span>' : '';
        if (!$definition->translatable()) {
            return '<div class="field"><div class="field-heading"><span>' . self::escape($label) . $required
                . '</span>' . ($help === '' ? '' : '<small>' . self::escape($help) . '</small>') . '</div>'
                . $this->control($definition, $languages, $value, $name, $depth) . '</div>';
        }

        $localized = $this->mapping($value);
        $tabs = '';
        $panels = '';
        foreach ($languages->languages() as $locale => $languageName) {
            $active = $locale === $languages->default();
            $tabs .= '<button type="button" class="locale-tab' . ($active ? ' active' : '')
                . '" data-locale-target="' . self::escape($locale) . '">' . self::escape($languageName) . '</button>';
            $panels .= '<div class="locale-panel' . ($active ? ' active' : '') . '" data-locale-panel="'
                . self::escape($locale) . '">' . $this->control(
                    $definition,
                    $languages,
                    $localized[$locale] ?? null,
                    $name . '[' . $locale . ']',
                    $depth,
                ) . '</div>';
        }

        return '<div class="field translated"><div class="field-heading"><span>' . self::escape($label) . $required
            . '</span>' . ($help === '' ? '' : '<small>' . self::escape($help) . '</small>')
            . '</div><div class="locale-tabs">' . $tabs . '</div>' . $panels . '</div>';
    }

    private function control(
        FieldDefinition $definition,
        LanguageConfig $languages,
        mixed $value,
        string $name,
        int $depth,
    ): string {
        $type = $definition->type();
        if ($type === 'repeater') {
            return $this->repeater($definition, $languages, $value, $name, $depth);
        }
        if (\in_array($type, ['image', 'file'], true)) {
            return $this->media($definition, $languages, $value, $name);
        }
        if ($type === 'boolean') {
            $checked = $value === true || $value === 'true' || $value === '1';

            return '<label class="switch"><input type="hidden" name="' . self::escape($name)
                . '" value="false"><input type="checkbox" name="' . self::escape($name)
                . '" value="true"' . ($checked ? ' checked' : '') . '><span>Włączone</span></label>';
        }
        if (\in_array($type, ['select', 'multiselect'], true)) {
            return $this->choice($definition, $languages, $value, $name, $type === 'multiselect');
        }
        if (\in_array($type, ['textarea', 'markdown'], true)) {
            $class = $type === 'markdown' ? ' class="markdown-input" data-markdown-editor' : '';

            return '<textarea' . $class . ' name="' . self::escape($name) . '"'
                . $this->attributes($definition, $languages) . '>'
                . self::escape(\is_string($value) ? $value : '') . '</textarea>';
        }

        $inputType = match ($type) {
            'number', 'url', 'email', 'date', 'color' => $type,
            'datetime' => 'datetime-local',
            default => 'text',
        };

        return '<input type="' . $inputType . '" name="' . self::escape($name) . '" value="'
            . self::escape(\is_int($value) || \is_float($value) ? (string) $value : (\is_string($value) ? $value : ''))
            . '"' . $this->attributes($definition, $languages) . '>';
    }

    private function repeater(
        FieldDefinition $definition,
        LanguageConfig $languages,
        mixed $value,
        string $name,
        int $depth,
    ): string {
        $items = \is_array($value) && array_is_list($value) ? $value : [];
        $rows = '';
        foreach ($items as $index => $item) {
            $rows .= $this->repeaterItem(
                $definition,
                $languages,
                $this->mapping($item),
                $name . '[' . $index . ']',
                $depth,
            );
        }
        $token = '__INDEX_' . $depth . '__';
        $template = $this->repeaterItem(
            $definition,
            $languages,
            [],
            $name . '[' . $token . ']',
            $depth,
        );

        return '<div class="repeater" data-repeater data-next-index="' . \count($items) . '"><div data-repeater-items>'
            . $rows . '</div><template data-repeater-template data-index-token="' . $token . '">' . $template
            . '</template><button type="button" class="button subtle" data-repeater-add>Dodaj element</button></div>';
    }

    /** @param array<string, mixed> $values */
    private function repeaterItem(
        FieldDefinition $definition,
        LanguageConfig $languages,
        array $values,
        string $prefix,
        int $depth,
    ): string {
        return '<fieldset class="repeater-item"><legend>Element</legend><button type="button" class="icon-button danger-text" '
            . 'data-repeater-remove aria-label="Usuń element">Usuń</button>'
            . $this->fields($definition->fields(), $languages, $values, $prefix, $depth + 1) . '</fieldset>';
    }

    private function media(
        FieldDefinition $definition,
        LanguageConfig $languages,
        mixed $value,
        string $name,
    ): string {
        $media = $this->mapping($value);
        $src = \is_string($media['src'] ?? null) ? $media['src'] : '';
        $html = '<div class="media-field-control" data-media-field><input type="text" data-media-source name="'
            . self::escape($name . '[src]') . '" value="' . self::escape($src)
            . '" placeholder="np. hero.jpg"><button type="button" class="button secondary" data-media-open data-media-kind="'
            . self::escape($definition->type()) . '">Wybierz z biblioteki</button></div>';
        if ($definition->type() !== 'image') {
            return $html;
        }
        $alt = $this->mapping($media['alt'] ?? null);
        $html .= '<div class="alt-grid">';
        foreach ($languages->languages() as $locale => $languageName) {
            $text = \is_string($alt[$locale] ?? null) ? $alt[$locale] : '';
            $html .= '<label>ALT — ' . self::escape($languageName) . '<input type="text" name="'
                . self::escape($name . '[alt][' . $locale . ']') . '" value="' . self::escape($text) . '"></label>';
        }

        return $html . '</div>';
    }

    private function choice(
        FieldDefinition $definition,
        LanguageConfig $languages,
        mixed $value,
        string $name,
        bool $multiple,
    ): string {
        $selected = $multiple && \is_array($value) ? $value : [$value];
        $options = '';
        foreach ($this->options($definition, $languages->default()) as $optionValue => $label) {
            $options .= '<option value="' . self::escape($optionValue) . '"'
                . (\in_array($optionValue, $selected, true) ? ' selected' : '') . '>' . self::escape($label) . '</option>';
        }

        return '<select name="' . self::escape($name . ($multiple ? '[]' : '')) . '"'
            . ($multiple ? ' multiple' : '') . '>'
            . (!$multiple && !$definition->required() ? '<option value="">— wybierz —</option>' : '')
            . $options . '</select>';
    }

    private function attributes(FieldDefinition $definition, LanguageConfig $languages): string
    {
        $settings = $definition->settings();
        $attributes = '';
        $placeholder = $this->uiText($definition, 'placeholder', $languages->default(), '');
        if ($placeholder !== '') {
            $attributes .= ' placeholder="' . self::escape($placeholder) . '"';
        }
        foreach (['minLength' => 'minlength', 'maxLength' => 'maxlength', 'min' => 'min', 'max' => 'max'] as $key => $attribute) {
            $setting = $settings[$key] ?? null;
            if (\is_int($setting) || \is_float($setting)) {
                $attributes .= ' ' . $attribute . '="' . self::escape((string) $setting) . '"';
            }
        }
        if ($definition->type() === 'number' && ($settings['integer'] ?? false) === true) {
            $attributes .= ' step="1"';
        }

        return $attributes;
    }

    /** @return array<string, string> */
    private function options(FieldDefinition $definition, string $locale): array
    {
        $raw = $definition->settings()['options'] ?? $definition->settings()['allowedValues'] ?? [];
        $options = [];
        if (!\is_array($raw)) {
            return $options;
        }
        foreach ($raw as $item) {
            if (\is_string($item)) {
                $options[$item] = $item;

                continue;
            }
            if (!\is_array($item) || !\is_string($item['value'] ?? null)) {
                continue;
            }
            $label = $item['label'] ?? $item['value'];
            if (\is_array($label)) {
                $label = $label[$locale] ?? reset($label);
            }
            $options[$item['value']] = \is_string($label) ? $label : $item['value'];
        }

        return $options;
    }

    private function uiText(FieldDefinition $definition, string $field, string $locale, string $fallback): string
    {
        $value = $definition->settings()[$field] ?? null;
        if (\is_string($value)) {
            return $value;
        }
        if (!\is_array($value)) {
            return $fallback;
        }
        $localized = $value[$locale] ?? reset($value);

        return \is_string($localized) ? $localized : $fallback;
    }

    /** @return array<string, mixed> */
    private function mapping(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            return [];
        }
        $mapping = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $mapping[$key] = $item;
            }
        }

        return $mapping;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
