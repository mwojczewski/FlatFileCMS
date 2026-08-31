<?php

declare(strict_types=1);

namespace FlatFileCms\Redirects;

use FlatFileCms\Content\InvalidContentException;
use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Infrastructure\Filesystem\FilesystemRoot;
use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Support\ContentData;
use InvalidArgumentException;

final readonly class RedirectRepository
{
    private const string FILE = 'redirects.yml';

    public function __construct(private YamlFileRepository $yaml) {}

    public function get(): RedirectDocument
    {
        $document = $this->yaml->read(FilesystemRoot::Config, RelativePath::fromString(self::FILE));

        return $this->fromData($document->data(), $document->revision());
    }

    /** @param list<RedirectRule> $rules */
    public function update(array $rules, FileRevision $revision): RedirectDocument
    {
        $data = [
            'schemaVersion' => 1,
            'redirects' => array_map(static fn(RedirectRule $rule): array => $rule->toArray(), $rules),
        ];
        $validated = $this->fromData($data, $revision);
        $written = $this->yaml->write(
            FilesystemRoot::Config,
            RelativePath::fromString(self::FILE),
            $data,
            $revision,
        );

        return new RedirectDocument($validated->rules(), $written->revision());
    }

    /** @param array<string, mixed> $data */
    private function fromData(array $data, FileRevision $revision): RedirectDocument
    {
        try {
            if (ContentData::integer($data['schemaVersion'] ?? null, 'schemaVersion') !== 1) {
                throw new InvalidArgumentException('Unsupported redirects schema version.');
            }
            $rules = [];
            $sources = [];
            $ids = [];
            foreach (ContentData::list($data['redirects'] ?? null, 'redirects') as $index => $value) {
                $path = "redirects.{$index}";
                $item = ContentData::map($value, $path);
                $rule = new RedirectRule(
                    $this->id(ContentData::string($item['id'] ?? null, "{$path}.id")),
                    $this->source(ContentData::string($item['source'] ?? null, "{$path}.source")),
                    $this->target(ContentData::string($item['target'] ?? null, "{$path}.target")),
                    $this->status(ContentData::integer($item['status'] ?? null, "{$path}.status")),
                    ContentData::boolean($item['enabled'] ?? null, "{$path}.enabled"),
                );
                if (isset($ids[$rule->id()]) || isset($sources[$rule->source()])) {
                    throw new InvalidArgumentException('Redirect identifiers and source paths must be unique.');
                }
                if ($rule->source() === $rule->target()) {
                    throw new InvalidArgumentException('A redirect cannot target its own source path.');
                }
                $ids[$rule->id()] = true;
                $sources[$rule->source()] = $rule->target();
                $rules[] = $rule;
            }
            $this->assertNoCycles($sources);

            return new RedirectDocument($rules, $revision);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidContentException('Invalid redirects.yml configuration.', previous: $exception);
        }
    }

    private function id(string $id): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Redirect identifier must be a UUID v7.');
        }

        return $id;
    }

    private function source(string $source): string
    {
        if ($source !== '/' && (preg_match('#^/(?:[A-Za-z0-9._~-]+/)*[A-Za-z0-9._~-]+$#D', $source) !== 1 || str_contains($source, '..'))) {
            throw new InvalidArgumentException('Redirect source must be a normalized absolute site path.');
        }

        return $source;
    }

    private function target(string $target): string
    {
        if ($target === '' || str_contains($target, "\0") || preg_match('/[\x01-\x1F\x7F\\\\]/', $target) === 1) {
            throw new InvalidArgumentException('Redirect target contains unsafe characters.');
        }
        if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            $path = parse_url($target, PHP_URL_PATH);
            if (!\is_string($path) || \in_array('..', explode('/', $path), true)) {
                throw new InvalidArgumentException('Redirect target path is invalid.');
            }

            return $target;
        }
        $scheme = parse_url($target, PHP_URL_SCHEME);
        if (filter_var($target, FILTER_VALIDATE_URL) === false || !\in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Redirect target must be a site path or absolute HTTP(S) URL.');
        }

        return $target;
    }

    private function status(int $status): int
    {
        if (!\in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException('Redirect status is not supported.');
        }

        return $status;
    }

    /** @param array<string, string> $targets */
    private function assertNoCycles(array $targets): void
    {
        foreach (array_keys($targets) as $source) {
            $visited = [];
            $current = $source;
            while (isset($targets[$current])) {
                if (isset($visited[$current])) {
                    throw new InvalidArgumentException('Redirect rules contain a cycle.');
                }
                $visited[$current] = true;
                $target = $targets[$current];
                $current = str_starts_with($target, '/') ? (parse_url($target, PHP_URL_PATH) ?: '/') : '';
                if ($current === '') {
                    break;
                }
            }
        }
    }
}
