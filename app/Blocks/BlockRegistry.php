<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use FilesystemIterator;
use FlatFileCms\Blocks\Field\FieldTypeRegistry;
use FlatFileCms\Domain\Content\Slug;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Yaml\InvalidYamlException;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;
use SplFileInfo;

final class BlockRegistry
{
    private const int MAX_DEFINITION_BYTES = 262_144;

    /** @var array<string, BlockDefinition>|null */
    private ?array $definitions = null;
    private string $blocksRoot;

    public function __construct(
        string $projectRoot,
        private readonly YamlParser $yaml,
        private readonly FieldTypeRegistry $fieldTypes,
        private readonly ?YamlFileCache $cache = null,
    ) {
        $blocksRoot = realpath(rtrim($projectRoot, '/\\') . '/blocks');
        if ($blocksRoot === false || !is_dir($blocksRoot)) {
            throw new InvalidBlockDefinitionException('Blocks directory is unavailable.');
        }

        $this->blocksRoot = $blocksRoot;
    }

    /** @return array<string, BlockDefinition> */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];
        $iterator = new FilesystemIterator($this->blocksRoot, FilesystemIterator::SKIP_DOTS);
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isDir() || $item->isLink() || str_starts_with($item->getFilename(), '.')) {
                continue;
            }

            try {
                $type = Slug::fromString($item->getFilename())->value();
                $definition = $this->load($type, $item->getPathname());
            } catch (FieldValueException|InvalidArgumentException|InvalidYamlException $exception) {
                throw new InvalidBlockDefinitionException(
                    sprintf('Block directory "%s" contains an invalid definition.', $item->getFilename()),
                    previous: $exception,
                );
            }

            $definitions[$type] = $definition;
        }

        ksort($definitions);
        $this->definitions = $definitions;

        return $definitions;
    }

    public function get(string $type): BlockDefinition
    {
        try {
            $type = Slug::fromString($type)->value();
        } catch (InvalidArgumentException $exception) {
            throw new InvalidBlockDefinitionException('Block type is invalid.', previous: $exception);
        }

        return $this->all()[$type]
            ?? throw new InvalidBlockDefinitionException(sprintf('Unknown block type "%s".', $type));
    }

    private function load(string $type, string $directory): BlockDefinition
    {
        $definitionPath = $directory . '/block.yml';
        $rendererPath = $directory . '/render.php';
        if (
            !is_file($definitionPath)
            || is_link($definitionPath)
            || !is_file($rendererPath)
            || is_link($rendererPath)
        ) {
            throw new InvalidBlockDefinitionException(
                sprintf('Block "%s" requires regular block.yml and render.php files.', $type),
            );
        }

        $size = filesize($definitionPath);
        if ($size === false || $size > self::MAX_DEFINITION_BYTES) {
            throw new InvalidBlockDefinitionException(sprintf('Block "%s" definition is too large.', $type));
        }
        $contents = file_get_contents($definitionPath);
        if ($contents === false) {
            throw new InvalidBlockDefinitionException(sprintf('Block "%s" definition cannot be read.', $type));
        }
        clearstatcache(true, $definitionPath);
        $modifiedAt = filemtime($definitionPath);
        if ($modifiedAt === false) {
            throw new InvalidBlockDefinitionException(sprintf('Block "%s" modification time cannot be read.', $type));
        }
        $rendererModifiedAt = filemtime($rendererPath);
        if ($rendererModifiedAt === false) {
            throw new InvalidBlockDefinitionException(sprintf('Block "%s" renderer time cannot be read.', $type));
        }
        $modifiedAt = max($modifiedAt, $rendererModifiedAt);

        $revision = FileRevision::fromContents($contents);
        $cacheKey = 'block-definition:' . $type;
        $data = $this->cache?->get($cacheKey, $revision);
        if ($data === null) {
            $data = $this->yaml->parse($contents);
            $this->cache?->put($cacheKey, $revision, $data);
        }
        if (ContentData::integer($data['schemaVersion'] ?? null, 'schemaVersion') !== 1) {
            throw new InvalidArgumentException('Unsupported block schema version.');
        }

        return new BlockDefinition(
            $type,
            $this->localizedUiText($data['name'] ?? null, 'name', required: true),
            $this->localizedUiText($data['description'] ?? [], 'description'),
            isset($data['icon']) ? ContentData::string($data['icon'], 'icon') : null,
            $this->fields($data['fields'] ?? [], 'fields'),
            $directory,
            $rendererPath,
            $modifiedAt,
        );
    }

    /** @return array<string, FieldDefinition> */
    private function fields(mixed $value, string $path): array
    {
        $mapping = ContentData::map($value, $path);
        $fields = [];
        foreach ($mapping as $name => $rawDefinition) {
            if (preg_match('/^[a-z][A-Za-z0-9_]*$/D', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('Field name "%s" is invalid.', $name));
            }

            $definition = ContentData::map($rawDefinition, $path . '.' . $name);
            $type = ContentData::string($definition['type'] ?? null, $path . '.' . $name . '.type');
            if (!$this->fieldTypes->has($type)) {
                throw new InvalidArgumentException(sprintf('Field "%s" uses unknown type "%s".', $name, $type));
            }
            $required = ContentData::boolean($definition['required'] ?? false, $path . '.' . $name . '.required');
            $translatable = ContentData::boolean(
                $definition['translatable'] ?? false,
                $path . '.' . $name . '.translatable',
            );
            $nested = isset($definition['fields'])
                ? $this->fields($definition['fields'], $path . '.' . $name . '.fields')
                : [];
            if ($type === 'repeater' && $nested === []) {
                throw new InvalidArgumentException(sprintf('Repeater field "%s" requires nested fields.', $name));
            }
            if ($type !== 'repeater' && $nested !== []) {
                throw new InvalidArgumentException(sprintf('Only repeater field "%s" may define nested fields.', $name));
            }

            $fieldDefinition = new FieldDefinition(
                $name,
                $type,
                $required,
                $translatable,
                $definition,
                $nested,
            );
            $this->fieldTypes->get($type)->validateDefinition($fieldDefinition);
            foreach (['label', 'placeholder', 'help'] as $uiField) {
                if (isset($definition[$uiField])) {
                    $this->localizedUiText($definition[$uiField], $path . '.' . $name . '.' . $uiField);
                }
            }

            $fields[$name] = $fieldDefinition;
        }

        return $fields;
    }

    /** @return array<string, string> */
    private function localizedUiText(mixed $value, string $field, bool $required = false): array
    {
        $mapping = ContentData::map($value, $field);
        if ($required && $mapping === []) {
            throw new InvalidArgumentException(sprintf('Field "%s" cannot be empty.', $field));
        }

        $localized = [];
        foreach ($mapping as $locale => $text) {
            if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/D', $locale) !== 1) {
                throw new InvalidArgumentException(sprintf('Field "%s" contains an invalid locale.', $field));
            }

            $localized[$locale] = ContentData::string($text, $field . '.' . $locale);
        }

        return $localized;
    }
}
