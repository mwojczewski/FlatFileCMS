<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Auth;

use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Auth\PasswordPolicy;
use FlatFileCms\Auth\PasswordResetRepository;
use FlatFileCms\Auth\PasswordResetService;
use FlatFileCms\Auth\RateLimiter;
use FlatFileCms\Auth\Role;
use FlatFileCms\Auth\UserRepository;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Infrastructure\Database\Database;
use FlatFileCms\Infrastructure\Database\SchemaInstaller;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Tests\Support\InMemoryMailer;
use FlatFileCms\Tests\Support\TemporaryProject;
use FlatFileCms\Tests\Support\TestContentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetService::class)]
#[CoversClass(PasswordResetRepository::class)]
final class PasswordResetServiceTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
        $this->project->write('config/setup.yml', <<<'YAML'
schemaVersion: 1
site:
  name: Example
  url: https://cms.example.test
  defaultLayout: default
seo: { }
media: { }
YAML);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testItSendsAHashedOneTimeTokenAndChangesThePassword(): void
    {
        [$service, $users, $mailer, $database] = $this->service();
        $hasher = new PasswordHasher();
        $user = $users->create('admin@example.test', $hasher->hash('Old!Password1'), Role::Admin);

        $service->request('ADMIN@example.test', '192.0.2.20');

        $message = $mailer->lastMessage();
        self::assertSame('admin@example.test', $message['recipient']);
        self::assertMatchesRegularExpression(
            '~https://cms\.example\.test/admin/password/reset\?token=([A-Za-z0-9_-]{43})~',
            $message['text'],
        );
        $token = $this->tokenFrom($message['text']);

        $statement = $database->query('SELECT token_hash FROM password_reset_tokens');
        self::assertNotFalse($statement);
        $stored = $statement->fetchColumn();
        self::assertSame(hash('sha256', $token), $stored);
        self::assertNotSame($token, $stored);
        self::assertTrue($service->isValid($token));

        $service->reset($token, 'New!Password2', 'New!Password2', '192.0.2.20');

        self::assertTrue($hasher->verify('New!Password2', $users->get($user->id())->passwordHash()));
        self::assertFalse($service->isValid($token));
        $this->expectException(AuthenticationException::class);
        $service->reset($token, 'Another!Password3', 'Another!Password3', '192.0.2.20');
    }

    public function testUnknownAddressHasTheSameSilentOutcomeWithoutSendingMail(): void
    {
        [$service, , $mailer] = $this->service();

        $service->request('missing@example.test', '192.0.2.21');

        self::assertSame(0, $mailer->count());
    }

    /** @return array{PasswordResetService, UserRepository, InMemoryMailer, \PDO} */
    private function service(): array
    {
        $database = (new Database($this->project->path('storage/database/test.sqlite')))->connection();
        (new SchemaInstaller($database))->install();
        $paths = new SafePathResolver($this->project->path());
        $users = new UserRepository($database);
        $mailer = new InMemoryMailer();
        $yaml = TestContentFactory::yaml($this->project);

        return [
            new PasswordResetService(
                $users,
                new PasswordResetRepository($database, $users),
                new PasswordHasher(),
                new PasswordPolicy(),
                new RateLimiter($database, 'reset-test-secret', 5, 900),
                $mailer,
                new ConfigurationRepository($yaml, $paths),
                new AuditLogger($paths),
                3600,
            ),
            $users,
            $mailer,
            $database,
        ];
    }

    private function tokenFrom(string $message): string
    {
        $matched = \preg_match('~token=([A-Za-z0-9_-]{43})~', $message, $matches);
        if ($matched !== 1) {
            self::fail('Password reset token is missing from the email.');
        }

        return $matches[1];
    }
}
