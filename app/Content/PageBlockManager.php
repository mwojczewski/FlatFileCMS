<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

use Closure;
use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidator;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\RevisionConflictException;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use FlatFileCms\Support\UuidV7;
use InvalidArgumentException;

final readonly class PageBlockManager
{
    public function __construct(
        private YamlFileRepository $yaml,
        private PageRepository $pages,
        private BlockRegistry $registry,
        private BlockValidator $validator,
        private BlockProcessor $processor,
        private FileLockManager $locks,
    ) {}

    public function editable(PageIdentity $identity): EditablePage
    {
        $document = $this->yaml->read(FilesystemRoot::Pages, $this->contentPath($identity));

        return new EditablePage($identity, $document->data(), $document->revision());
    }

    /** @return array<string, mixed> */
    public function block(PageIdentity $identity, string $id): array
    {
        foreach ($this->blocks($this->editable($identity)->data()) as $block) {
            if (($block['id'] ?? null) === $id) {
                return $block;
            }
        }

        throw new InvalidArgumentException('Block does not exist on this page.');
    }

    /** @param array<string, mixed> $data */
    public function add(
        PageIdentity $identity,
        string $type,
        array $data,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): EditablePage {
        return $this->mutate(
            $identity,
            $expectedRevision,
            $languages,
            function (array $blocks) use ($type, $data, $languages, $identity): array {
                $definition = $this->registry->get($type);
                $normalized = $this->validator->validate($definition, $data, $languages, $identity);
                $blocks[] = [
                    'id' => UuidV7::generate(),
                    'type' => $definition->type(),
                    'enabled' => true,
                    'data' => $normalized->values(),
                ];

                return $blocks;
            },
        );
    }

    /** @param array<string, mixed> $data */
    public function update(
        PageIdentity $identity,
        string $id,
        array $data,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): EditablePage {
        return $this->mutate(
            $identity,
            $expectedRevision,
            $languages,
            function (array $blocks) use ($id, $data, $languages, $identity): array {
                $index = $this->indexOf($blocks, $id);
                $type = ContentData::string($blocks[$index]['type'] ?? null, 'block.type');
                $normalized = $this->validator->validate(
                    $this->registry->get($type),
                    $data,
                    $languages,
                    $identity,
                );
                $blocks[$index]['data'] = $normalized->values();

                return $blocks;
            },
        );
    }

    public function duplicate(
        PageIdentity $identity,
        string $id,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): EditablePage {
        return $this->mutate(
            $identity,
            $expectedRevision,
            $languages,
            function (array $blocks) use ($id): array {
                $index = $this->indexOf($blocks, $id);
                $copy = $blocks[$index];
                $copy['id'] = UuidV7::generate();
                array_splice($blocks, $index + 1, 0, [$copy]);

                return $blocks;
            },
        );
    }

    public function toggle(
        PageIdentity $identity,
        string $id,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): EditablePage {
        return $this->mutate(
            $identity,
            $expectedRevision,
            $languages,
            function (array $blocks) use ($id): array {
                $index = $this->indexOf($blocks, $id);
                $enabled = $blocks[$index]['enabled'] ?? true;
                if (!\is_bool($enabled)) {
                    throw new InvalidContentException('Block enabled state is invalid.');
                }
                $blocks[$index]['enabled'] = !$enabled;

                return $blocks;
            },
        );
    }

    /** @param list<string> $orderedIds */
    public function reorder(
        PageIdentity $identity,
        array $orderedIds,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): EditablePage {
        return $this->mutate(
            $identity,
            $expectedRevision,
            $languages,
            function (array $blocks) use ($orderedIds): array {
                if (\count($orderedIds) !== \count(array_unique($orderedIds))) {
                    throw new InvalidArgumentException('Block order contains duplicate identifiers.');
                }
                $byId = [];
                foreach ($blocks as $block) {
                    $id = ContentData::string($block['id'] ?? null, 'block.id');
                    $byId[$id] = $block;
                }
                if (\count($orderedIds) !== \count($byId)) {
                    throw new InvalidArgumentException('Block order must contain every page block exactly once.');
                }
                $ordered = [];
                foreach ($orderedIds as $id) {
                    if (!isset($byId[$id])) {
                        throw new InvalidArgumentException('Block order contains an unknown identifier.');
                    }
                    $ordered[] = $byId[$id];
                }

                return $ordered;
            },
        );
    }

    public function delete(
        PageIdentity $identity,
        string $id,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
    ): EditablePage {
        return $this->mutate(
            $identity,
            $expectedRevision,
            $languages,
            function (array $blocks) use ($id): array {
                $index = $this->indexOf($blocks, $id);
                array_splice($blocks, $index, 1);

                return $blocks;
            },
        );
    }

    /**
     * @param Closure(list<array<string, mixed>>): list<array<string, mixed>> $operation
     */
    private function mutate(
        PageIdentity $identity,
        FileRevision $expectedRevision,
        LanguageConfig $languages,
        Closure $operation,
    ): EditablePage {
        return $this->locks->exclusive('page-blocks:' . $identity->value(), function () use ($identity, $expectedRevision, $languages, $operation): EditablePage {
            $editable = $this->editable($identity);
            if (!$editable->revision()->equals($expectedRevision)) {
                throw new RevisionConflictException($expectedRevision, $editable->revision());
            }
            $data = $editable->data();
            $data['blocks'] = $operation($this->blocks($data));
            $page = $this->pages->fromData(
                $identity,
                $data,
                $languages,
                FileRevision::missing(),
                time(),
            );
            foreach ($languages->codes() as $locale) {
                $this->processor->forPublicPage($page, $locale, $languages);
            }
            $document = $this->yaml->write(
                FilesystemRoot::Pages,
                $this->contentPath($identity),
                $data,
                $expectedRevision,
            );

            return new EditablePage($identity, $document->data(), $document->revision());
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function blocks(array $data): array
    {
        $rawBlocks = ContentData::list($data['blocks'] ?? [], 'blocks');
        $blocks = [];
        foreach ($rawBlocks as $index => $rawBlock) {
            $blocks[] = ContentData::map($rawBlock, "blocks.{$index}");
        }

        return $blocks;
    }

    /** @param list<array<string, mixed>> $blocks */
    private function indexOf(array $blocks, string $id): int
    {
        foreach ($blocks as $index => $block) {
            if (($block['id'] ?? null) === $id) {
                return $index;
            }
        }

        throw new InvalidArgumentException('Block does not exist on this page.');
    }

    private function contentPath(PageIdentity $identity): RelativePath
    {
        return RelativePath::fromString($identity->value() . '/content.yml');
    }
}
