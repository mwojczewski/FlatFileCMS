<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Unit\Auth;

use FlatFileCms\Auth\ArraySessionStore;
use FlatFileCms\Auth\AdminUserManager;
use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\PasswordChanger;
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
#[CoversClass(PasswordChanger::class)]
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

    public function testAuthenticatedUserCanChangePassword(): void
    {
        [$users] = $this->repositories();
        $hasher = new PasswordHasher();
        $user = $users->create('admin@example.test', $hasher->hash('Old!Password1'), Role::Admin);
        $changer = new PasswordChanger($users, $hasher, new PasswordPolicy());

        $updated = $changer->change($user, 'Old!Password1', 'New!Password2', 'New!Password2');

        self::assertTrue($hasher->verify('New!Password2', $updated->passwordHash()));
        self::assertFalse($hasher->verify('Old!Password1', $updated->passwordHash()));
    }

    public function testPasswordChangeRejectsInvalidCurrentPassword(): void
    {
        [$users] = $this->repositories();
        $hasher = new PasswordHasher();
        $user = $users->create('admin@example.test', $hasher->hash('Old!Password1'), Role::Admin);
        $changer = new PasswordChanger($users, $hasher, new PasswordPolicy());

        $this->expectException(AuthenticationException::class);
        $changer->change($user, 'Wrong!Password1', 'New!Password2', 'New!Password2');
    }

    public function testAdminUserManagerCreatesAndUpdatesOnlyAdminAccounts(): void
    {
        [$users] = $this->repositories();
        $hasher = new PasswordHasher();
        $actor = $users->create('actor@example.test', $hasher->hash('Valid!Password1'), Role::Admin);
        $manager = new AdminUserManager($users, new PasswordPolicy(), $hasher);

        $created = $manager->create($actor, 'new@example.test', 'Valid!Password2', 'Valid!Password2');
        $updated = $manager->update($actor, $created->id(), 'edited@example.test', false, '', '');

        self::assertSame(Role::Admin, $created->role());
        self::assertSame('edited@example.test', $updated->email());
        self::assertFalse($updated->enabled());
    }

    public function testAdminUserManagerCannotDeleteSelfOrTechnicalSuperadmin(): void
    {
        [$users] = $this->repositories();
        $hasher = new PasswordHasher();
        $actor = $users->create('actor@example.test', $hasher->hash('Valid!Password1'), Role::Admin);
        $superadmin = $users->create('root@example.test', $hasher->hash('Valid!Password1'), Role::Superadmin);
        $manager = new AdminUserManager($users, new PasswordPolicy(), $hasher);

        try {
            $manager->delete($actor, $actor->id());
            self::fail('Expected self deletion to be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertNotNull($users->findByEmail('actor@example.test'));
        }

        $this->expectException(\FlatFileCms\Auth\UserNotFoundException::class);
        $manager->delete($actor, $superadmin->id());
    }

    /** @return array{UserRepository, WebAuthnCredentialRepository, \PDO} */
    private function repositories(): array
    {
        $database = (new Database($this->project->path('storage/database/test.sqlite')))->connection();
        (new SchemaInstaller($database))->install();

        return [new UserRepository($database), new WebAuthnCredentialRepository($database), $database];
    }
}
