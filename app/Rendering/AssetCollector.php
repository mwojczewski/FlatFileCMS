<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Support\ContentData;

final readonly class AssetCollector
{
    public function __construct(
        private BlockRegistry $registry,
        private AssetPublisher $publisher,
    ) {}

    /** @param list<array<string, mixed>> $blocks */
    public function collect(array $blocks): AssetCollection
    {
        $styles = [];
        $scripts = [];
        $modifiedAt = 0;
        $types = [];

        foreach ($blocks as $index => $block) {
            $type = ContentData::string($block['type'] ?? null, 'blocks.' . $index . '.type');
            if (isset($types[$type])) {
                continue;
            }
            $types[$type] = true;

            $definition = $this->registry->get($type);
            foreach (['style.css' => 'style', 'script.js' => 'script'] as $filename => $kind) {
                $source = $definition->directory() . '/' . $filename;
                if (!is_file($source)) {
                    continue;
                }
                if (is_link($source)) {
                    throw new RenderingException(sprintf('Block "%s" asset cannot be a symlink.', $type));
                }

                $url = $this->publisher->publish($type, $source);
                if ($kind === 'style') {
                    $styles[] = $url;
                } else {
                    $scripts[] = $url;
                }

                $mtime = filemtime($source);
                if ($mtime === false) {
                    throw new RenderingException('Block asset modification time cannot be read.');
                }
                $modifiedAt = max($modifiedAt, $mtime);
            }
        }

        return new AssetCollection($styles, $scripts, $modifiedAt);
    }
}
