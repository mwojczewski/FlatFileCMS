<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FilesystemIterator;
use FlatFileCms\Domain\Content\Slug;
use InvalidArgumentException;
use SplFileInfo;

final class PartialRegistry
{
    private string $root;

    /** @var array<string, string>|null */
    private ?array $partials = null;

    public function __construct(string $projectRoot)
    {
        $root = realpath(rtrim($projectRoot, '/\\') . '/templates/partials');
        if ($root === false || !is_dir($root)) {
            throw new RenderingException('Partials directory is unavailable.');
        }

        $this->root = $root;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->partials !== null) {
            return $this->partials;
        }

        $partials = [];
        $iterator = new FilesystemIterator($this->root, FilesystemIterator::SKIP_DOTS);
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->isLink() || $item->getExtension() !== 'php') {
                continue;
            }

            $name = pathinfo($item->getFilename(), PATHINFO_FILENAME);
            try {
                $name = Slug::fromString($name)->value();
            } catch (InvalidArgumentException $exception) {
                throw new RenderingException('Partial filename is invalid.', previous: $exception);
            }

            $partials[$name] = $item->getPathname();
        }

        ksort($partials);
        $this->partials = $partials;

        return $partials;
    }

    public function get(string $name): string
    {
        try {
            $name = Slug::fromString($name)->value();
        } catch (InvalidArgumentException $exception) {
            throw new RenderingException('Partial name is invalid.', previous: $exception);
        }

        return $this->all()[$name]
            ?? throw new RenderingException(sprintf('Unknown partial "%s".', $name));
    }

    public function modifiedAt(): int
    {
        $modifiedAt = 0;
        foreach ($this->all() as $path) {
            $value = filemtime($path);
            if ($value === false) {
                throw new RenderingException('Partial modification time cannot be read.');
            }

            $modifiedAt = max($modifiedAt, $value);
        }

        return $modifiedAt;
    }
}
