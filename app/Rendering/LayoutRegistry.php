<?php

declare(strict_types=1);

namespace FlatFileCms\Rendering;

use FilesystemIterator;
use FlatFileCms\Domain\Content\Slug;
use InvalidArgumentException;
use SplFileInfo;

final class LayoutRegistry
{
    private string $root;

    /** @var array<string, string>|null */
    private ?array $layouts = null;

    public function __construct(string $projectRoot)
    {
        $root = realpath(rtrim($projectRoot, '/\\') . '/templates/layouts');
        if ($root === false || !is_dir($root)) {
            throw new RenderingException('Layouts directory is unavailable.');
        }

        $this->root = $root;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->layouts !== null) {
            return $this->layouts;
        }

        $layouts = [];
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
                throw new RenderingException('Layout filename is invalid.', previous: $exception);
            }

            $layouts[$name] = $item->getPathname();
        }

        ksort($layouts);
        $this->layouts = $layouts;

        return $layouts;
    }

    public function get(string $name): string
    {
        try {
            $name = Slug::fromString($name)->value();
        } catch (InvalidArgumentException $exception) {
            throw new RenderingException('Layout name is invalid.', previous: $exception);
        }

        return $this->all()[$name]
            ?? throw new RenderingException(\sprintf('Unknown layout "%s".', $name));
    }

    public function modifiedAt(string $name): int
    {
        $modifiedAt = filemtime($this->get($name));
        if ($modifiedAt === false) {
            throw new RenderingException('Layout modification time cannot be read.');
        }

        return $modifiedAt;
    }
}
