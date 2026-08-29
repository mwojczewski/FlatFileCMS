<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Core;

use FlatFileCms\Core\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/flatfile-cms-env-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0o700, true);
    }

    protected function tearDown(): void
    {
        $file = $this->temporaryDirectory . '/.env.local';
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($this->temporaryDirectory);
    }

    public function testItLoadsLocalValuesAndNormalizesDebugFlag(): void
    {
        file_put_contents($this->temporaryDirectory . '/.env.local', "APP_ENV=testing\nAPP_DEBUG=true\n");

        $environment = Environment::load($this->temporaryDirectory);

        self::assertSame('testing', $environment->name());
        self::assertTrue($environment->debug());
    }

    public function testItRejectsInvalidBooleanValue(): void
    {
        file_put_contents($this->temporaryDirectory . '/.env.local', "YAML_CACHE_ENABLED=perhaps\n");
        $environment = Environment::load($this->temporaryDirectory);

        $this->expectException(RuntimeException::class);
        $environment->boolean('YAML_CACHE_ENABLED', true);
    }
}
