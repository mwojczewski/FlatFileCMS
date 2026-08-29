<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Filesystem;

final class SafePathResolver
{
    private string $projectRoot;

    /** @var array<string, string> */
    private array $resolvedRoots = [];

    public function __construct(string $projectRoot)
    {
        $resolvedProjectRoot = realpath($projectRoot);
        if ($resolvedProjectRoot === false || !is_dir($resolvedProjectRoot)) {
            throw new FilesystemException('Project root does not exist or is not a directory.');
        }

        $this->projectRoot = $resolvedProjectRoot;
    }

    public function resolve(FilesystemRoot $root, RelativePath $relativePath, bool $mustExist = false): string
    {
        $rootPath = $this->rootPath($root);
        $currentPath = $rootPath;

        foreach ($relativePath->segments() as $segment) {
            if (file_exists($currentPath) && !is_dir($currentPath)) {
                throw new FilesystemException('A parent path is not a directory.');
            }

            $candidate = $currentPath . DIRECTORY_SEPARATOR . $segment;
            if (file_exists($candidate) || is_link($candidate)) {
                $resolvedCandidate = realpath($candidate);
                if ($resolvedCandidate === false) {
                    throw new FilesystemException('Path contains a broken or inaccessible symbolic link.');
                }

                $this->assertWithinRoot($rootPath, $resolvedCandidate);
                $currentPath = $resolvedCandidate;

                continue;
            }

            $currentPath = $candidate;
        }

        $this->assertWithinRoot($rootPath, $currentPath);

        if ($mustExist && !file_exists($currentPath)) {
            throw new FilesystemException('Required filesystem path does not exist.');
        }

        return $currentPath;
    }

    public function rootPath(FilesystemRoot $root): string
    {
        if (isset($this->resolvedRoots[$root->value])) {
            return $this->resolvedRoots[$root->value];
        }

        $candidate = $this->projectRoot . DIRECTORY_SEPARATOR . $root->value;
        $resolved = realpath($candidate);
        if ($resolved === false || !is_dir($resolved)) {
            throw new FilesystemException(sprintf('Filesystem root "%s" is unavailable.', $root->value));
        }

        $this->assertWithinRoot($this->projectRoot, $resolved);
        $this->resolvedRoots[$root->value] = $resolved;

        return $resolved;
    }

    private function assertWithinRoot(string $root, string $candidate): void
    {
        $normalizedRoot = $this->normalizeForComparison($root);
        $normalizedCandidate = $this->normalizeForComparison($candidate);

        if (
            $normalizedCandidate !== $normalizedRoot
            && !str_starts_with($normalizedCandidate, $normalizedRoot . '/')
        ) {
            throw new PathEscapeException('Resolved path escapes its allowed filesystem root.');
        }
    }

    private function normalizeForComparison(string $path): string
    {
        $normalized = str_replace('\\', '/', rtrim($path, '/\\'));

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
    }
}
