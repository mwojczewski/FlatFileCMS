<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Yaml;

use FilesystemIterator;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemException;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CompiledYamlCache
{
    private const string ROOT = 'cache/compiled';
    private const string MANIFEST = self::ROOT . '/manifest.php';

    /** @var array{documents: array<string, string>}|null */
    private ?array $manifest = null;
    /** @var array<string, array{revision: string, data: array<string, mixed>}>|null */
    private ?array $configuration = null;

    public function __construct(
        private readonly bool $enabled,
        private readonly string $release,
        private readonly SafePathResolver $paths,
        private readonly AtomicFileWriter $writer,
        private readonly YamlParser $parser,
    ) {}

    public function get(string $key): ?YamlDocument
    {
        if (!$this->enabled) {
            return null;
        }
        $path = $this->manifest()['documents'][$key] ?? null;
        if ($path === null) {
            return null;
        }
        $entry = str_starts_with($key, 'config:')
            ? ($this->configuration($path)[$key] ?? null)
            : $this->entry($this->readPhp($path, 'Compiled YAML document is missing.'));
        if ($entry === null) {
            throw new FilesystemException('Compiled YAML manifest references a missing document.');
        }

        return new YamlDocument($entry['data'], FileRevision::fromString($entry['revision']));
    }

    public function warmUp(): int
    {
        $documents = $this->sources();
        $revisions = array_map(static fn(array $entry): string => $entry['revision'], $documents);
        $generation = substr(hash('sha256', $this->release . "\0" . json_encode($revisions, JSON_THROW_ON_ERROR)), 0, 24);
        $base = self::ROOT . '/generations/' . $generation;
        $index = [];
        $config = [];
        foreach ($documents as $key => $entry) {
            if (str_starts_with($key, 'config:')) {
                $config[$key] = $entry;
                $index[$key] = $base . '/config.php';
                continue;
            }
            $family = str_starts_with($key, 'pages:') ? 'pages' : 'errors';
            $path = $base . '/' . $family . '/' . hash('sha256', $key) . '.php';
            $this->writePhp($path, $entry);
            $index[$key] = $path;
        }
        if ($config !== []) {
            $this->writePhp($base . '/config.php', $config);
        }
        ksort($index);
        $this->writePhp(self::MANIFEST, ['version' => 2, 'release' => $this->release, 'documents' => $index]);
        $this->manifest = ['documents' => $index];
        $this->configuration = $config;

        return \count($documents);
    }

    public function refresh(): void
    {
        if ($this->enabled) {
            $this->warmUp();
        }
    }

    /** @return array<string, array{revision: string, data: array<string, mixed>}> */
    private function sources(): array
    {
        $documents = [];
        foreach ([FilesystemRoot::Config, FilesystemRoot::Pages, FilesystemRoot::Errors] as $root) {
            $directory = $this->paths->rootPath($root);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            /** @var SplFileInfo $item */
            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->isLink() || preg_match('/\.ya?ml$/D', $item->getFilename()) !== 1) {
                    continue;
                }
                $contents = file_get_contents($item->getPathname());
                if ($contents === false) {
                    throw new FilesystemException('Unable to read YAML while warming compiled cache.');
                }
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), \strlen($directory) + 1));
                $documents[$root->value . ':' . $relative] = [
                    'revision' => FileRevision::fromContents($contents)->value(),
                    'data' => $this->parser->parse($contents),
                ];
            }
        }
        ksort($documents);

        return $documents;
    }

    /** @return array{documents: array<string, string>} */
    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }
        $record = $this->readPhp(self::MANIFEST, 'Compiled YAML cache is missing. Run php bin/cms cache:warmup.');
        if (($record['version'] ?? null) !== 2 || ($record['release'] ?? null) !== $this->release || !\is_array($record['documents'] ?? null)) {
            throw new FilesystemException('Compiled YAML cache is stale. Run php bin/cms cache:warmup.');
        }
        $documents = [];
        foreach ($record['documents'] as $key => $path) {
            if (!\is_string($key) || !\is_string($path) || !str_starts_with($path, self::ROOT . '/generations/')) {
                throw new FilesystemException('Compiled YAML manifest is invalid.');
            }
            $documents[$key] = $path;
        }

        return $this->manifest = ['documents' => $documents];
    }

    /** @return array<string, array{revision: string, data: array<string, mixed>}> */
    private function configuration(string $path): array
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }
        $configuration = [];
        foreach ($this->readPhp($path, 'Compiled YAML configuration is missing.') as $key => $entry) {
            if (!\is_string($key)) {
                throw new FilesystemException('Compiled YAML configuration is invalid.');
            }
            $configuration[$key] = $this->entry($entry);
        }

        return $this->configuration = $configuration;
    }

    /** @return array<string|int, mixed> */
    private function readPhp(string $relative, string $missing): array
    {
        $path = $this->paths->resolve(FilesystemRoot::Storage, RelativePath::fromString($relative));
        if (!is_file($path) || is_link($path)) {
            throw new FilesystemException($missing);
        }
        $data = require $path;
        if (!\is_array($data)) {
            throw new FilesystemException('Compiled YAML PHP file must return an array.');
        }

        return $data;
    }

    /** @return array{revision: string, data: array<string, mixed>} */
    private function entry(mixed $entry): array
    {
        if (!\is_array($entry) || !\is_string($entry['revision'] ?? null) || !\is_array($entry['data'] ?? null)) {
            throw new FilesystemException('Compiled YAML document is invalid.');
        }
        FileRevision::fromString($entry['revision']);
        $data = [];
        foreach ($entry['data'] as $key => $value) {
            if (!\is_string($key)) {
                throw new FilesystemException('Compiled YAML document root contains a non-string key.');
            }
            $data[$key] = $value;
        }

        return ['revision' => $entry['revision'], 'data' => $data];
    }

    /** @param array<mixed> $data */
    private function writePhp(string $relative, array $data): void
    {
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($data, true) . ";\n";
        $this->writer->write(FilesystemRoot::Storage, RelativePath::fromString($relative), $contents);
    }
}
