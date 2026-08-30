<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use InvalidArgumentException;

final readonly class PasswordChanger
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private PasswordPolicy $policy,
    ) {}

    public function change(
        User $user,
        string $currentPassword,
        string $newPassword,
        string $confirmation,
    ): User {
        if (!$this->hasher->verify($currentPassword, $user->passwordHash())) {
            throw new AuthenticationException('Current password is invalid.');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('New password confirmation does not match.');
        }
        if ($this->hasher->verify($newPassword, $user->passwordHash())) {
            throw new InvalidArgumentException('New password must be different from the current password.');
        }
        $this->policy->validate($newPassword);
        $this->users->updatePassword($user, $this->hasher->hash($newPassword));

        return $this->users->get($user->id());
    }
}
