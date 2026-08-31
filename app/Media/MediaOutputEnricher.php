<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\FieldDefinition;
use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Domain\Content\PageIdentity;
use InvalidArgumentException;

final readonly class MediaOutputEnricher
{
    public function __construct(
        private BlockRegistry $blocks,
        private MediaRepository $media,
        private MediaUrlGenerator $urls,
    ) {}

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public function enrich(PageIdentity $identity, array $blocks): array
    {
        try {
            foreach ($blocks as $index => $block) {
                $type = $block['type'] ?? null;
                $data = $block['data'] ?? null;
                if (!\is_string($type) || !\is_array($data) || ($data !== [] && array_is_list($data))) {
                    throw new InvalidArgumentException('Normalized block output is invalid.');
                }

                $blocks[$index]['data'] = $this->fields(
                    $identity,
                    $this->blocks->get($type)->fields(),
                    $this->stringMapping($data),
                );
            }
        } catch (InvalidArgumentException|MediaException $exception) {
            throw new InvalidContentException(
                \sprintf('Page "%s" contains an invalid media reference.', $identity->value()),
                previous: $exception,
            );
        }

        return $blocks;
    }

    public function modifiedAt(PageIdentity $identity): int
    {
        return $this->media->modifiedAt($identity);
    }

    /**
     * @param array<string, FieldDefinition> $definitions
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function fields(PageIdentity $identity, array $definitions, array $values): array
    {
        foreach ($definitions as $name => $definition) {
            if (!\array_key_exists($name, $values)) {
                continue;
            }

            if ($definition->type() === 'image' || $definition->type() === 'file') {
                $values[$name] = $this->mediaValue($identity, $values[$name]);

                continue;
            }

            if ($definition->type() !== 'repeater' || !\is_array($values[$name])) {
                continue;
            }

            $items = [];
            foreach ($values[$name] as $item) {
                if (!\is_array($item) || ($item !== [] && array_is_list($item))) {
                    throw new InvalidArgumentException('Normalized repeater output is invalid.');
                }
                $items[] = $this->fields($identity, $definition->fields(), $this->stringMapping($item));
            }
            $values[$name] = $items;
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private function mediaValue(PageIdentity $identity, mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException('Normalized media output is invalid.');
        }
        $mapping = $this->stringMapping($value);
        $source = $mapping['src'] ?? null;
        if (!\is_string($source)) {
            throw new InvalidArgumentException('Normalized media output requires src.');
        }

        $item = $this->media->get($identity, MediaName::fromString($source))->item();
        $mapping['url'] = $this->urls->original($identity, $item);
        $mapping['mimeType'] = $item->mimeType();
        $mapping['size'] = $item->size();
        $mapping['fingerprint'] = $item->fingerprint();
        if ($item->width() !== null && $item->height() !== null) {
            $mapping['width'] = $item->width();
            $mapping['height'] = $item->height();
        }

        return $mapping;
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    private function stringMapping(array $value): array
    {
        $mapping = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Normalized mapping keys must be strings.');
            }
            $mapping[$key] = $item;
        }

        return $mapping;
    }
}
