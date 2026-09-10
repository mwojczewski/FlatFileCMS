<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Cli;

use FlatFileCms\Analytics\AnalyticsCache;
use FlatFileCms\Analytics\AnalyticsHttpClient;
use FlatFileCms\Analytics\CloudflareAnalyticsConfig;
use FlatFileCms\Analytics\CloudflareAnalyticsService;
use FlatFileCms\Analytics\CloudflareGraphQlClient;
use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Auth\PasswordPolicy;
use FlatFileCms\Auth\Role;
use FlatFileCms\Auth\UserRepository;
use FlatFileCms\Auth\WebAuthnCredentialRepository;
use FlatFileCms\Cli\BlockScaffolder;
use FlatFileCms\Cli\CacheClearer;
use FlatFileCms\Cli\CachePruner;
use FlatFileCms\Cli\ConsoleApplication;
use FlatFileCms\Cli\PasswordReader;
use FlatFileCms\Cli\RuntimePruner;
use FlatFileCms\Cli\UserCommandService;
use FlatFileCms\Infrastructure\Database\Database;
use FlatFileCms\Infrastructure\Database\SchemaInstaller;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

#[CoversClass(ConsoleApplication::class)]
final class ConsoleApplicationTest extends TestCase
{
    public function testUserCreateAcceptsFirstAndLastNameOptions(): void
    {
        $project = TemporaryProject::create();
        putenv('CMS_PASSWORD=Strong!Password1');
        $paths = new SafePathResolver($project->path());
        $database = new Database($project->path('storage/database/test.sqlite'));
        $connection = $database->connection();
        $repository = new UserRepository($connection);
        $service = new UserCommandService(
            new SchemaInstaller($connection),
            $repository,
            new WebAuthnCredentialRepository($connection),
            new PasswordPolicy(),
            new PasswordHasher(),
            new AuditLogger($paths),
        );

        $application = new ConsoleApplication(
            new BlockScaffolder($project->path()),
            static fn(): UserCommandService => $service,
            new PasswordReader(),
            new CacheClearer($paths),
            new CachePruner($paths),
            new RuntimePruner($paths),
            new CloudflareAnalyticsService(
                new CloudflareGraphQlClient(
                    new class implements AnalyticsHttpClient {
            public function postJson(string $url, array $payload, string $token, int $timeout): string
            {
                throw new \RuntimeException('No Cloudflare call expected in this test.');
            }
                    },
                    new CloudflareAnalyticsConfig(false, '', '', '', '', '', 600, 10, 'Europe/Warsaw'),
                ),
                new CloudflareAnalyticsConfig(false, '', '', '', '', '', 600, 10, 'Europe/Warsaw'),
                new AnalyticsCache($project->path()),
                new class extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
            }
                },
            ),
            static fn(): object => throw new \RuntimeException('Release check not used.'),
        );

        $status = $application->run([
            'bin/cms',
            'user:create',
            'jan@example.test',
            '--first-name',
            'Jan',
            '--last-name',
            'Kowalski',
        ]);

        self::assertSame(0, $status);

        $created = $repository->findByEmail('jan@example.test');
        self::assertNotNull($created);
        self::assertSame('Jan', $created->firstName());
        self::assertSame('Kowalski', $created->lastName());
        self::assertSame(Role::Admin, $created->role());

        putenv('CMS_PASSWORD');
        $project->remove();
    }
}
