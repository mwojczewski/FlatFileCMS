<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Yaml;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlParser
{
    public function __construct(
        private int $maxBytes = 1_048_576,
        private int $maxDepth = 32,
        private int $maxNodes = 20_000,
    ) {
        if ($this->maxBytes < 1 || $this->maxDepth < 1 || $this->maxNodes < 1) {
            throw new InvalidYamlException('YAML parser limits must be positive integers.');
        }
    }

    /** @return array<string, mixed> */
    public function parse(string $contents): array
    {
        if (\strlen($contents) > $this->maxBytes) {
            throw new InvalidYamlException('YAML document exceeds the configured size limit.');
        }

        try {
            $parsed = Yaml::parse(
                $contents,
                Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_EXCEPTION_ON_ALIAS,
                $this->maxDepth,
                0,
            );
        } catch (ParseException $exception) {
            throw new InvalidYamlException('YAML document is malformed.', previous: $exception);
        }

        if (!\is_array($parsed)) {
            throw new InvalidYamlException('YAML document root must be a mapping.');
        }

        foreach (array_keys($parsed) as $key) {
            if (!\is_string($key)) {
                throw new InvalidYamlException('YAML document root keys must be strings.');
            }
        }

        $nodeCount = 0;
        $this->assertSupportedNode($parsed, 1, $nodeCount);

        /** @var array<string, mixed> $parsed */
        return $parsed;
    }

    private function assertSupportedNode(mixed $node, int $depth, int &$nodeCount): void
    {
        ++$nodeCount;
        if ($nodeCount > $this->maxNodes) {
            throw new InvalidYamlException('YAML document contains too many nodes.');
        }

        if ($depth > $this->maxDepth) {
            throw new InvalidYamlException('YAML document exceeds the configured nesting depth.');
        }

        if ($node === null || \is_bool($node) || \is_int($node)) {
            return;
        }

        if (\is_float($node)) {
            if (!is_finite($node)) {
                throw new InvalidYamlException('YAML document contains a non-finite number.');
            }

            return;
        }

        if (\is_string($node)) {
            if (!mb_check_encoding($node, 'UTF-8')) {
                throw new InvalidYamlException('YAML document contains invalid UTF-8 text.');
            }

            return;
        }

        if (!\is_array($node)) {
            throw new InvalidYamlException('YAML document contains an unsupported value type.');
        }

        foreach ($node as $value) {
            $this->assertSupportedNode($value, $depth + 1, $nodeCount);
        }
    }
}
