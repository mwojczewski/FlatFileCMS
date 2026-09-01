<?php

declare(strict_types=1);

namespace FlatFileCms\Content;

use FilesystemIterator;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ContentFileIndex
{
    /** @var array{pages: list<string>, collections: list<string>}|null */
    private ?array $index = null;

    public function __construct(private readonly SafePathResolver $paths) {}

    /** @return list<string> */
    public function pages(): array
    {
        return $this->scan()['pages'];
    }

    /** @return list<string> */
    public function collections(): array
    {
        return $this->scan()['collections'];
    }

    public function invalidate(): void
    {
        $this->index = null;
    }

    /** @return array{pages: list<string>, collections: list<string>} */
    private function scan(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $root = $this->paths->rootPath(FilesystemRoot::Pages);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        $pages = [];
        $collections = [];
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->isLink()) {
                continue;
            }
            $filename = $item->getFilename();
            if (!\in_array($filename, ['content.yml', 'pagination.yml'], true)) {
                continue;
            }
            $directory = \dirname($item->getPathname());
            $identity = str_replace(DIRECTORY_SEPARATOR, '/', substr($directory, \strlen($root) + 1));
            if ($filename === 'content.yml') {
                $pages[] = $identity;
            } else {
                $collections[] = $identity;
            }
        }
        sort($pages);
        sort($collections);

        return $this->index = ['pages' => $pages, 'collections' => $collections];
    }
}
