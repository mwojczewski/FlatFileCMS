<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Core;

use FlatFileCms\Core\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    public function testItCreatesEachSharedServiceOnce(): void
    {
        $container = new Container();
        $container->set(stdClass::class, static fn(): stdClass => new stdClass());

        self::assertSame($container->get(stdClass::class), $container->get(stdClass::class));
    }

    public function testItRejectsUnknownServices(): void
    {
        $container = new Container();

        $this->expectException(RuntimeException::class);
        $container->get(stdClass::class);
    }
}
