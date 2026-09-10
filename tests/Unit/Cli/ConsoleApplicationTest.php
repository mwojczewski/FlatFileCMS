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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

#[CoversClass(ConsoleApplication::class)]
final class ConsoleApplicationTest extends TestCase
{
    public function testUserCreateAcceptsFirstAndLastNameOptions(): void
    {
        $project = TemporaryProject::create();
        putenv('CMS_PASSWORD=Strong!Password1');

        try {
            [$application, $repository] = $this->userApplication($project);

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
        } finally {
            putenv('CMS_PASSWORD');
            $project->remove();
        }
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function incompleteNameOptions(): iterable
    {
        yield 'only first name' => [
            [
                'bin/cms',
                'user:create',
                'jan@example.test',
                '--first-name',
                'Jan',
            ],
        ];

        yield 'only last name' => [
            [
                'bin/cms',
                'user:create',
                'jan@example.test',
                '--last-name',
                'Kowalski',
            ],
        ];
    }

    /** @param list<string> $arguments */
    #[DataProvider('incompleteNameOptions')]
    public function testUserCreateRejectsIncompleteName(
        array $arguments,
    ): void {
        $project = TemporaryProject::create();
        putenv('CMS_PASSWORD=Strong!Password1');

        try {
            [$application, $repository] = $this->userApplication($project);

            self::assertSame(1, $application->run($arguments));
            self::assertNull($repository->findByEmail('jan@example.test'));
        } finally {
            putenv('CMS_PASSWORD');
            $project->remove();
        }
    }

    /** @return array{ConsoleApplication, UserRepository} */
    private function userApplication(TemporaryProject $project): array
    {
        $paths = new SafePathResolver($project->path());
        $database = new Database($project->path('storage/database/test.sqlite'));
        $connection = $database->connection();
        $repository = new UserRepository($connection);
        $schema = new SchemaInstaller($connection);
        $schema->install();
        $service = new UserCommandService(
            $schema,
            $repository,
            new WebAuthnCredentialRepository($connection),
            new PasswordPolicy(),
            new PasswordHasher(),
            new AuditLogger($paths),
        );

        return [
            $this->application($project, $service, $paths),
            $repository,
        ];
    }

    private function application(
        TemporaryProject $project,
        UserCommandService $service,
        SafePathResolver $paths,
    ): ConsoleApplication {
        $analyticsConfig = new CloudflareAnalyticsConfig(
            false,
            '',
            '',
            '',
            '',
            '',
            600,
            10,
            'Europe/Warsaw',
        );

        return new ConsoleApplication(
            new BlockScaffolder($project->path()),
            static fn(): UserCommandService => $service,
            new PasswordReader(),
            new CacheClearer($paths),
            new CachePruner($paths),
            new RuntimePruner($paths),
            new CloudflareAnalyticsService(
                new CloudflareGraphQlClient(
                    new class implements AnalyticsHttpClient {
                        public function postJson(
                            string $url,
                            array $payload,
                            string $token,
                            int $timeout,
                        ): string {
                            throw new \RuntimeException(
                                'No Cloudflare call expected in this test.',
                            );
                        }
                    },
                    $analyticsConfig,
                ),
                $analyticsConfig,
                new AnalyticsCache($project->path()),
                new class extends AbstractLogger {
                    public function log(
                        $level,
                        string|\Stringable $message,
                        array $context = [],
                    ): void {}
                },
            ),
            static fn(): object => throw new \RuntimeException(
                'Release check not used.',
            ),
        );
    }
}
