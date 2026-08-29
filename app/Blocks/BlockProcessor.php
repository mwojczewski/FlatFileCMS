<?php

declare(strict_types=1);

namespace FlatFileCms\Blocks;

use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class BlockProcessor
{
    public function __construct(
        private BlockRegistry $registry,
        private BlockValidator $validator,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forPublicPage(Page $page, string $locale, LanguageConfig $languages): array
    {
        try {
            $result = [];
            $identifiers = [];
            foreach ($page->blocks() as $index => $block) {
                $path = 'blocks.' . $index;
                foreach (array_keys($block) as $property) {
                    if (!in_array($property, ['id', 'type', 'enabled', 'data'], true)) {
                        throw new InvalidArgumentException(sprintf('Unknown property "%s" at %s.', $property, $path));
                    }
                }

                $id = strtolower(ContentData::string($block['id'] ?? null, $path . '.id'));
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id) !== 1) {
                    throw new InvalidArgumentException(sprintf('Block identifier at %s must be UUID v7.', $path));
                }
                if (isset($identifiers[$id])) {
                    throw new InvalidArgumentException(sprintf('Duplicate block identifier "%s".', $id));
                }
                $identifiers[$id] = true;

                $type = ContentData::string($block['type'] ?? null, $path . '.type');
                $enabled = ContentData::boolean($block['enabled'] ?? true, $path . '.enabled');
                $data = ContentData::map($block['data'] ?? [], $path . '.data');
                $definition = $this->registry->get($type);
                $normalized = $this->validator->validate(
                    $definition,
                    $data,
                    $languages,
                    $page->identity(),
                );

                if (!$enabled) {
                    continue;
                }

                $result[] = [
                    'id' => $id,
                    'type' => $definition->type(),
                    'data' => $this->validator->localize(
                        $definition,
                        $normalized,
                        $locale,
                        $languages,
                        $page->identity(),
                    ),
                ];
            }

            return $result;
        } catch (InvalidArgumentException|InvalidBlockDefinitionException|BlockValidationException $exception) {
            throw new InvalidContentException(
                sprintf('Page "%s" contains invalid block data.', $page->identity()->value()),
                previous: $exception,
            );
        }
    }

    public function definitionsModifiedAt(Page $page): int
    {
        try {
            $modifiedAt = 0;
            foreach ($page->blocks() as $index => $block) {
                $type = ContentData::string($block['type'] ?? null, 'blocks.' . $index . '.type');
                $modifiedAt = max($modifiedAt, $this->registry->get($type)->modifiedAt());
            }

            return $modifiedAt;
        } catch (InvalidArgumentException|InvalidBlockDefinitionException $exception) {
            throw new InvalidContentException(
                sprintf('Page "%s" contains an invalid block type.', $page->identity()->value()),
                previous: $exception,
            );
        }
    }
}
