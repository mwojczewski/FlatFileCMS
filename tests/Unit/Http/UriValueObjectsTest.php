<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Http;

use FlatFileCms\Blocks\Field\ContentUrl;
use FlatFileCms\Http\PublicHttpUrl;
use FlatFileCms\Http\RequestTarget;
use FlatFileCms\Navigation\NavigationLink;
use FlatFileCms\Seo\CanonicalReference;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UriValueObjectsTest extends TestCase
{
    #[DataProvider('validPublicUrls')]
    public function testItAcceptsStrictPublicHttpUrls(string $value): void
    {
        self::assertSame($value, PublicHttpUrl::fromString($value)->value());
    }

    /** @return iterable<string, array{string}> */
    public static function validPublicUrls(): iterable
    {
        yield 'HTTPS scheme' => ['https://example.com/path?draft=1'];
        yield 'HTTP scheme' => ['http://example.com:8080/path'];
        yield 'Unicode host' => ['https://żółć.example/path'];
    }

    #[DataProvider('invalidPublicUrls')]
    public function testItRejectsUnsafeOrNonHttpPublicUrls(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        PublicHttpUrl::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPublicUrls(): iterable
    {
        yield 'relative path' => ['/path'];
        yield 'scheme relative' => ['//evil.example/path'];
        yield 'credentials' => ['https://user:password@example.com/path'];
        yield 'backslash repair' => ['https://example.com\\evil'];
        yield 'space in host' => ['https://exa mple.com'];
        yield 'script scheme' => ['javascript:alert(1)'];
    }

    public function testRequestTargetPreservesItsRawEncodedPath(): void
    {
        self::assertSame('/caf%C3%A9', RequestTarget::path('/caf%C3%A9?draft=1'));
        self::assertSame('/a/../b', RequestTarget::path('/a/../b'));
        self::assertSame('/', RequestTarget::path('https://evil.example/path'));
    }

    public function testCanonicalReferenceResolvesAPathAgainstTheSiteRoot(): void
    {
        $base = PublicHttpUrl::fromString('https://example.com/subdirectory');

        self::assertSame(
            'https://example.com/articles?draft=1',
            CanonicalReference::fromString('/articles?draft=1')->absolute($base),
        );
    }

    public function testCanonicalReferenceRejectsFragments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CanonicalReference::fromString('https://example.com/article#fragment');
    }

    public function testNavigationLinksUseSchemeSpecificValidation(): void
    {
        self::assertSame('mailto:editor@example.com', NavigationLink::fromString('mailto:editor@example.com')->value());
        self::assertSame('tel:+48123456789', NavigationLink::fromString('tel:+48123456789')->value());

        $this->expectException(InvalidArgumentException::class);
        NavigationLink::fromString('mailto:not-an-email');
    }

    public function testContentUrlDoesNotBroadenItsContractToMailAndTelephoneLinks(): void
    {
        self::assertTrue(ContentUrl::isValid('/contact'));
        self::assertTrue(ContentUrl::isValid('https://example.com/contact'));
        self::assertFalse(ContentUrl::isValid('mailto:editor@example.com'));
        self::assertFalse(ContentUrl::isValid('tel:+48123456789'));
    }
}
