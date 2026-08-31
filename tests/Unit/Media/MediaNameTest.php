<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Media;

use FlatFileCms\Media\MediaName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaNameTest extends TestCase
{
    #[DataProvider('invalidNames')]
    public function testItRejectsUnsafeNames(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        MediaName::fromString($name);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'traversal' => ['../photo.jpg'];
        yield 'subdirectory' => ['nested/photo.jpg'];
        yield 'hidden' => ['.photo.jpg'];
        yield 'yaml' => ['content.yml'];
        yield 'double dot' => ['photo..jpg'];
    }
}
