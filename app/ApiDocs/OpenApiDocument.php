<?php

declare(strict_types=1);

namespace FlatFileCms\ApiDocs;

use JsonException;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class OpenApiDocument
{
    public function __construct(private string $filename) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        if (!is_file($this->filename) || !is_readable($this->filename)) {
            throw new RuntimeException('OpenAPI document is not readable.');
        }

        try {
            $document = Yaml::parseFile($this->filename);
        } catch (ParseException $exception) {
            throw new RuntimeException('OpenAPI document contains invalid YAML.', previous: $exception);
        }

        if (!\is_array($document)) {
            throw new RuntimeException('OpenAPI document must be a mapping.');
        }
        if (!isset($document['openapi']) || !\is_string($document['openapi'])) {
            throw new RuntimeException('OpenAPI document must declare its version.');
        }
        if (!isset($document['info']) || !\is_array($document['info'])) {
            throw new RuntimeException('OpenAPI document must contain info.');
        }
        if (!isset($document['paths']) || !\is_array($document['paths'])) {
            throw new RuntimeException('OpenAPI document must contain paths.');
        }

        /** @var array<string, mixed> $document */
        return $document;
    }

    /** @throws JsonException */
    public function json(): string
    {
        return json_encode(
            $this->data(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        );
    }

    public function modifiedAt(): int
    {
        $modifiedAt = filemtime($this->filename);
        if ($modifiedAt === false) {
            throw new RuntimeException('OpenAPI document modification time cannot be read.');
        }

        return $modifiedAt;
    }
}
