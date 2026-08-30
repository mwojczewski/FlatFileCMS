<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Auth;

use FlatFileCms\Auth\ArraySessionStore;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Auth\PasswordPolicy;
use FlatFileCms\Auth\RateLimiter;
use FlatFileCms\Auth\Role;
use FlatFileCms\Auth\User;
use FlatFileCms\Auth\UserRepository;
use FlatFileCms\Auth\WebAuthnCredentialRepository;
use FlatFileCms\Infrastructure\Database\Database;
use FlatFileCms\Infrastructure\Database\SchemaInstaller;
use FlatFileCms\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Authenticator::class)]
#[CoversClass(UserRepository::class)]
final class AuthenticationTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::create();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testAdminCannotDiscoverSuperadminsThroughRepository(): void
    {
        [$users] = $this->repositories();
        $hash = (new PasswordHasher())->hash('Valid!Password1');
        $admin = $users->create('admin@example.test', $hash, Role::Admin);
        $superadmin = $users->create('root@example.test', $hash, Role::Superadmin);

        self::assertSame(
            [$admin->id()],
            array_map(static fn(User $user): int => $user->id(), $users->visibleTo($admin)),
        );
        $this->expectException(\FlatFileCms\Auth\UserNotFoundException::class);
        $users->getVisibleTo($superadmin->id(), $admin);
    }

    public function testRegisteredSecurityKeyMakesSecondFactorMandatory(): void
    {
        [$users, $credentials, $database] = $this->repositories();
        $hasher = new PasswordHasher();
        $user = $users->create('admin@example.test', $hasher->hash('Valid!Password1'), Role::Admin);
        $credentials->add($user, 'YubiKey', random_bytes(32), 'public-key', 0, ['usb']);
        $session = new ArraySessionStore();
        $authenticator = new Authenticator(
            $users,
            $credentials,
            $hasher,
            $session,
            new RateLimiter($database, 'test-secret', 5, 900),
        );

        self::assertTrue($authenticator->passwordLogin('admin@example.test', 'Valid!Password1'));
        self::assertNull($authenticator->user());
        self::assertSame($user->id(), $authenticator->pendingUser()->id());
        $authenticator->complete($user);
        self::assertSame($user->id(), $authenticator->requireUser()->id());
    }

    public function testPasswordPolicyRequiresAllConfiguredCharacterClasses(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PasswordPolicy())->validate('onlylowercase');
    }

    /** @return array{UserRepository, WebAuthnCredentialRepository, \PDO} */
    private function repositories(): array
    {
        $database = (new Database($this->project->path('storage/database/test.sqlite')))->connection();
        (new SchemaInstaller($database))->install();

        return [new UserRepository($database), new WebAuthnCredentialRepository($database), $database];
    }
}
