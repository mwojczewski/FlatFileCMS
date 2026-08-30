<?php

declare(strict_types=1);

namespace FlatFileCms\Cli;

use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Auth\PasswordPolicy;
use FlatFileCms\Auth\Role;
use FlatFileCms\Auth\UserRepository;
use FlatFileCms\Auth\WebAuthnCredentialRepository;
use FlatFileCms\Infrastructure\Database\SchemaInstaller;
use RuntimeException;

final readonly class UserCommandService
{
    public function __construct(
        private SchemaInstaller $schema,
        private UserRepository $users,
        private WebAuthnCredentialRepository $credentials,
        private PasswordPolicy $policy,
        private PasswordHasher $hasher,
    ) {}

    public function install(string $email, string $password): void
    {
        $this->schema->install();
        if ($this->users->count() !== 0) {
            throw new RuntimeException('CMS already contains users.');
        }
        $this->create($email, $password, Role::Superadmin);
    }

    public function create(string $email, string $password, Role $role = Role::Admin): void
    {
        $this->schema->install();
        $this->policy->validate($password);
        $this->users->create($email, $this->hasher->hash($password), $role);
    }

    public function changePassword(string $email, string $password): void
    {
        $this->schema->install();
        $this->policy->validate($password);
        $user = $this->users->findByEmail($email) ?? throw new RuntimeException('User not found.');
        $this->users->updatePassword($user, $this->hasher->hash($password));
    }

    public function clearSecurityKeys(string $email): int
    {
        $this->schema->install();
        $user = $this->users->findByEmail($email) ?? throw new RuntimeException('User not found.');

        return $this->credentials->clearForUser($user->id());
    }
}
