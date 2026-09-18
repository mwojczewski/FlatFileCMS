<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Core;

use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Blocks\Field\TextFieldType;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ModernPhpFeaturesTest extends TestCase
{
    public function testImmutableRequestCloneWithMethodsOnlyChangeTheSelectedState(): void
    {
        $request = new Request('GET', '/articles', attributes: ['locale' => 'pl'], clientIp: '127.0.0.1');

        $changed = $request
            ->withAttributes(['page' => 'articles'])
            ->withClientIp('203.0.113.10');

        self::assertSame('pl', $changed->attribute('locale'));
        self::assertSame('articles', $changed->attribute('page'));
        self::assertSame('203.0.113.10', $changed->clientIp());
        self::assertSame('127.0.0.1', $request->clientIp());
        self::assertNull($request->attribute('page'));
    }

    public function testSensitiveParametersAreMarkedForRuntimeRedaction(): void
    {
        $parameter = (new ReflectionMethod(PasswordHasher::class, 'hash'))->getParameters()[0] ?? throw new \RuntimeException('Expected parameter to be present in method signature');

        self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class));
    }

    public function testInterfaceImplementationIsGuardedByOverride(): void
    {
        self::assertCount(1, (new ReflectionMethod(TextFieldType::class, 'normalize'))->getAttributes(\Override::class));
    }

    public function testCompatibilityGetterIsNativelyDeprecated(): void
    {
        self::assertTrue((new ReflectionMethod(HttpException::class, 'status'))->isDeprecated());
    }
}
