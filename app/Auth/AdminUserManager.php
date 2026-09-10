<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use InvalidArgumentException;

final readonly class AdminUserManager
{
    public function __construct(
        private UserRepository $users,
        private PasswordPolicy $passwordPolicy,
        private PasswordHasher $passwords,
    ) {}

    public function create(
        User $actor,
        string $email,
        string $password,
        string $confirmation,
        string $firstName = '',
        string $lastName = '',
    ): User {
        $this->validateProfile($firstName, $lastName);
        if ($password !== $confirmation) {
            throw new InvalidArgumentException('Password confirmation does not match.');
        }
        $this->passwordPolicy->validate($password);

        return $this->users->create(
            $email,
            $this->passwords->hash($password),
            Role::Admin,
            $firstName,
            $lastName,
        );
    }

    public function update(
        User $actor,
        int $id,
        string $email,
        bool $enabled,
        string $password,
        string $confirmation,
        ?string $firstName = null,
        ?string $lastName = null,
    ): User {
        $user = $this->adminVisibleTo($id, $actor);
        if ($user->id() === $actor->id() && !$enabled) {
            throw new InvalidArgumentException('You cannot disable your own account.');
        }
        if ($firstName !== null && $lastName !== null) {
            $this->validateProfile($firstName, $lastName);
        }
        $this->users->update($user, $email, $enabled, $firstName, $lastName);
        if ($password !== '' || $confirmation !== '') {
            if ($password !== $confirmation) {
                throw new InvalidArgumentException('Password confirmation does not match.');
            }
            $this->passwordPolicy->validate($password);
            $this->users->updatePassword($user, $this->passwords->hash($password));
        }

        return $this->users->getVisibleTo($id, $actor);
    }

    public function delete(User $actor, int $id): void
    {
        $user = $this->adminVisibleTo($id, $actor);
        if ($user->id() === $actor->id()) {
            throw new InvalidArgumentException('You cannot delete your own account.');
        }
        $this->users->delete($user);
    }

    private function adminVisibleTo(int $id, User $actor): User
    {
        $user = $this->users->getVisibleTo($id, $actor);
        if ($user->role() !== Role::Admin) {
            throw new UserNotFoundException('User not found.');
        }

        return $user;
    }

    private function validateProfile(string $firstName, string $lastName): void
    {
        if (trim($firstName) === '' || trim($lastName) === '') {
            throw new InvalidArgumentException('First name and last name are required.');
        }
    }
}
