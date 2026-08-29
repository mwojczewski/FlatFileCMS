<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Infrastructure\Filesystem;

use FlatFileCms\Infrastructure\Filesystem\RelativePath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelativePath::class)]
final class RelativePathTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function unsafePaths(): iterable
    {
        yield 'parent traversal' => ['../config/setup.yml'];
        yield 'nested traversal' => ['offer/../config.yml'];
        yield 'absolute Unix' => ['/etc/passwd'];
        yield 'absolute Windows' => ['C:\\Windows\\system.ini'];
        yield 'backslash' => ['offer\\content.yml'];
        yield 'null byte' => ["content.yml\0.php"];
        yield 'empty segment' => ['offer//content.yml'];
        yield 'reserved filename' => ['CON/content.yml'];
        yield 'alternate data stream' => ['content.yml:secret'];
        yield 'trailing dot' => ['content.yml.'];
        yield 'trailing space' => ['content.yml '];
    }

    #[DataProvider('unsafePaths')]
    public function testItRejectsUnsafePaths(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        RelativePath::fromString($value);
    }

    public function testItPreservesAValidPortablePath(): void
    {
        $path = RelativePath::fromString('offer/websites/content.yml');

        self::assertSame('offer/websites/content.yml', $path->value());
        self::assertSame(['offer', 'websites', 'content.yml'], $path->segments());
    }
}
