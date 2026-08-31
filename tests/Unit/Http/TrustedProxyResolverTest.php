<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Http;

use FlatFileCms\Http\TrustedProxyResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TrustedProxyResolverTest extends TestCase
{
    public function testItIgnoresForwardedHeadersFromUntrustedPeers(): void
    {
        $resolver = TrustedProxyResolver::fromString('10.0.0.0/8');

        self::assertSame('203.0.113.9', $resolver->resolve('203.0.113.9', '198.51.100.20'));
    }

    public function testItSelectsTheFirstUntrustedAddressFromAProxyChain(): void
    {
        $resolver = TrustedProxyResolver::fromString('10.0.0.0/8, 2001:db8:1::/48');

        self::assertSame(
            '198.51.100.20',
            $resolver->resolve('10.0.0.5', '198.51.100.20, 10.1.2.3'),
        );
        self::assertSame(
            '2001:db8:ffff::10',
            $resolver->resolve('2001:db8:1::5', '2001:db8:ffff::10'),
        );
    }

    public function testItRejectsInvalidTrustedNetworks(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TrustedProxyResolver::fromString('0.0.0.0/99');
    }
}
