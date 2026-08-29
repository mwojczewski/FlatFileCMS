<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Domain\Content;

use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Domain\Content\Slug;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slug::class)]
#[CoversClass(PageIdentity::class)]
final class SlugTest extends TestCase
{
    public function testItBuildsStableNestedPageIdentity(): void
    {
        $identity = PageIdentity::fromString('services/websites');

        self::assertSame('services/websites', $identity->value());
        self::assertFalse($identity->isHomepage());
        self::assertTrue(PageIdentity::homepage()->isHomepage());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Offer'];
        yield 'spaces' => ['our offer'];
        yield 'double hyphen' => ['our--offer'];
        yield 'unicode' => ['oferta-łódź'];
        yield 'slash' => ['offer/websites'];
    }

    #[DataProvider('invalidSlugs')]
    public function testItRejectsInvalidSlug(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Slug::fromString($value);
    }

    public function testHomepageCannotBeNested(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PageIdentity::fromString('company/homepage');
    }
}
