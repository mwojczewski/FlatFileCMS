<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Infrastructure\Yaml;

use FlatFileCms\Infrastructure\Yaml\InvalidYamlException;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlParser::class)]
final class YamlParserTest extends TestCase
{
    public function testItParsesAValidMapping(): void
    {
        $data = (new YamlParser())->parse("enabled: true\ntitle:\n  pl: Oferta\n");

        self::assertSame(true, $data['enabled']);
        self::assertSame(['pl' => 'Oferta'], $data['title']);
    }

    public function testItRejectsASequenceAtDocumentRoot(): void
    {
        $this->expectException(InvalidYamlException::class);
        (new YamlParser())->parse("- first\n- second\n");
    }

    public function testItRejectsExcessiveNesting(): void
    {
        $this->expectException(InvalidYamlException::class);
        (new YamlParser(maxDepth: 2))->parse("first:\n  second:\n    third: value\n");
    }

    public function testItRejectsOversizedInputBeforeParsing(): void
    {
        $this->expectException(InvalidYamlException::class);
        (new YamlParser(maxBytes: 8))->parse('title: too long');
    }

    public function testItRejectsAliasesBeforeExpansion(): void
    {
        $this->expectException(InvalidYamlException::class);
        (new YamlParser())->parse("defaults: &defaults\n  enabled: true\npage: *defaults\n");
    }
}
