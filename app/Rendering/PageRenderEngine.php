<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use InvalidArgumentException;

enum PageRenderEngine: string
{
    case Php = 'php';
    case ReactPrerender = 'react-prerender';

    /** @param array<string, mixed> $data */
    public static function fromPageData(array $data): self
    {
        $render = $data['render'] ?? null;
        if ($render === null) {
            return self::Php;
        }
        if (!\is_array($render) || ($render !== [] && array_is_list($render))) {
            throw new InvalidArgumentException('Page render configuration must be a mapping.');
        }

        $engine = $render['engine'] ?? null;
        if (!\is_string($engine)) {
            throw new InvalidArgumentException('Page render.engine must be a string.');
        }

        return self::tryFrom($engine)
            ?? throw new InvalidArgumentException(\sprintf('Unsupported page render engine "%s".', $engine));
    }
}
