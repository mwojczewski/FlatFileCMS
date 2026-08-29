# Blocks and field schemas

Blocks are developer-owned packages discovered automatically from `blocks/`.
Adding a standard block requires no router, controller, database, API or core
change.

```text
blocks/hero/
├── block.yml      required schema
├── render.php     required developer renderer
├── style.css      optional
├── script.js      optional
└── preview.webp   optional admin preview
```

Directories, `block.yml` and `render.php` must be regular files below the
hard-coded developer block root. Symlinked block packages and renderers are not
loaded. Administrators cannot edit any of these files.

## CLI scaffolding

```bash
php bin/cms block:create image-with-text
php bin/cms block:create image-with-text --with-assets
```

The command accepts the same lowercase ASCII slug as BlockRegistry. It creates
block.yml and render.php with translatable title and content fields. The
asset option additionally creates style.css and script.js. Generated files
are written in a private temporary directory and the complete package is then
published using a directory rename. Existing block directories are rejected and
never deliberately overwritten.
The generated PHP, YAML, CSS and JavaScript are ordinary source files. A
developer can immediately modify them in an editor; the CMS does not provide a
web editor for executable block code.

## Definition

```yaml
schemaVersion: 1
name:
  pl: Hero
  en: Hero
description:
  pl: Sekcja otwierająca
  en: Opening section
icon: image
fields:
  title:
    type: text
    required: true
    translatable: true
    minLength: 1
    maxLength: 160
    label:
      pl: Nagłówek
      en: Heading
```

Names of block directories are lowercase ASCII slugs. Field names begin with a
lowercase letter and may contain ASCII letters, digits and underscores.
Definitions are checked when the registry is first used; an unknown field type,
invalid rule or missing renderer rejects the package.

## Built-in field types

| Type                           | Normalized value                       | Rules                                               |
| ------------------------------ | -------------------------------------- | --------------------------------------------------- |
| `text`, `textarea`, `markdown` | string with normalized line endings    | `minLength`/`maxLength`, aliases `min`/`max`        |
| `number`                       | integer or finite float                | `min`, `max`, `integer`                             |
| `boolean`                      | boolean                                | accepts booleans and standard form boolean strings  |
| `select`                       | allowed string                         | `options` or `allowedValues`                        |
| `multiselect`                  | unique list of allowed strings         | options plus `minItems`/`maxItems`                  |
| `url`                          | absolute HTTP(S) or root-relative URL  | format validation                                   |
| `email`                        | email string                           | format validation                                   |
| `date`                         | `YYYY-MM-DD`                           | strict calendar validation                          |
| `datetime`                     | HTML datetime-local or RFC 3339 string | strict format validation                            |
| `color`                        | lowercase `#rrggbb` or `#rrggbbaa`     | format validation                                   |
| `image`                        | `{src, alt?}`                          | safe page-local path, existing image extension/file |
| `file`                         | `{src}`                                | safe page-local path and existing file              |
| `repeater`                     | list of normalized mappings            | `fields`, `minItems`/`maxItems`                     |

For choices, `options` may be a list of values or objects containing `value`
and localized `label`.

```yaml
options:
  - value: left
    label: { pl: Do lewej, en: Left }
  - value: center
    label: { pl: Środek, en: Center }
```

## Translation contract

`translatable: true` means the stored value is a locale mapping. Required fields
must contain a non-empty valid value for every enabled language. Optional fields
may omit translations; the API uses the default-locale value as fallback.

Repeaters apply the same rules recursively. An image field itself can remain
non-translatable while its ALT is localized at the usage site:

```yaml
image:
  src: hero.jpg
  alt:
    pl: Zespół w biurze
    en: Team in the office
```

The normalized API response contains only the requested ALT. The media file does
not own ALT metadata.

## Validation behavior

Validation rejects:

- unknown block types and data fields;
- missing required values or translations;
- values violating type, format, length, range or allowed-option rules;
- media paths escaping the page directory or referencing missing files;
- block IDs that are not UUID v7;
- duplicate block IDs anywhere on one page.

Disabled blocks are also validated, then omitted from public output. Validation
errors retain stable field paths such as `data.items.0.caption.en`, which the
future admin form layer can map directly to controls.

## Adding a custom field type

A custom type implements `FlatFileCms\\Blocks\\Field\\FieldType`:

```php
interface FieldType
{
    public function name(): string;
    public function validateDefinition(FieldDefinition $definition): void;
    public function normalize(mixed $value, FieldDefinition $definition, FieldContext $context): mixed;
    public function localize(mixed $value, string $locale, FieldDefinition $definition, FieldContext $context): mixed;
}
```

Register one shared instance in `FieldTypeRegistry` during bootstrap. No block,
controller, API serializer or database change is needed. The future dynamic
admin form registry will use the same stable type name.
