<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use DateTimeImmutable;
use PDO;
use PDOException;

final readonly class UserRepository
{
    public function __construct(private PDO $database) {}

    public function create(string $email, string $passwordHash, Role $role): User
    {
        $email = $this->normalizeEmail($email);
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $handle = random_bytes(32);
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO users (email, password_hash, role, enabled, webauthn_user_handle, created_at, updated_at, password_changed_at)
VALUES (:email, :password_hash, :role, 1, :handle, :created_at, :updated_at, :password_changed_at)
SQL);
        $statement->bindValue(':email', $email);
        $statement->bindValue(':password_hash', $passwordHash);
        $statement->bindValue(':role', $role->value);
        $statement->bindValue(':handle', $handle, PDO::PARAM_LOB);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);
        $statement->bindValue(':password_changed_at', $now);
        try {
            $statement->execute();
        } catch (PDOException $exception) {
            throw new AuthenticationException('An account with this email already exists.', previous: $exception);
        }

        return $this->get((int) $this->database->lastInsertId());
    }

    public function count(): int
    {
        $statement = $this->database->query('SELECT COUNT(*) FROM users');
        if ($statement === false) {
            throw new AuthenticationException('Unable to count users.');
        }

        return (int) $statement->fetchColumn();
    }

    public function get(int $id): User
    {
        $statement = $this->database->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!\is_array($row)) {
            throw new UserNotFoundException('User not found.');
        }

        return $this->hydrate($row);
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->database->prepare('SELECT * FROM users WHERE email = :email COLLATE NOCASE');
        $statement->execute(['email' => $this->normalizeEmail($email)]);
        $row = $statement->fetch();

        return \is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return list<User> */
    public function visibleTo(User $actor): array
    {
        $sql = $actor->role() === Role::Superadmin
            ? 'SELECT * FROM users ORDER BY email'
            : "SELECT * FROM users WHERE role = 'ROLE_ADMIN' ORDER BY email";
        $statement = $this->database->query($sql);
        if ($statement === false) {
            throw new AuthenticationException('Unable to list users.');
        }
        $rows = $statement->fetchAll();
        $users = [];
        foreach ($rows as $row) {
            if (\is_array($row)) {
                $users[] = $this->hydrate($row);
            }
        }

        return $users;
    }

    public function getVisibleTo(int $id, User $actor): User
    {
        $user = $this->get($id);
        if ($actor->role() === Role::Admin && $user->role() === Role::Superadmin) {
            throw new UserNotFoundException('User not found.');
        }

        return $user;
    }

    public function updatePassword(User $user, string $passwordHash): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $statement = $this->database->prepare(<<<'SQL'
UPDATE users SET password_hash = :hash, password_changed_at = :changed, updated_at = :updated WHERE id = :id
SQL);
        $statement->execute(['hash' => $passwordHash, 'changed' => $now, 'updated' => $now, 'id' => $user->id()]);
    }

    public function update(User $user, string $email, bool $enabled): void
    {
        $email = $this->normalizeEmail($email);
        $statement = $this->database->prepare(<<<'SQL'
UPDATE users SET email = :email, enabled = :enabled, updated_at = :updated WHERE id = :id
SQL);
        try {
            $statement->execute([
                'email' => $email,
                'enabled' => $enabled ? 1 : 0,
                'updated' => (new DateTimeImmutable())->format(DATE_ATOM),
                'id' => $user->id(),
            ]);
        } catch (PDOException $exception) {
            throw new AuthenticationException('An account with this email already exists.', previous: $exception);
        }
    }

    public function delete(User $user): void
    {
        if ($user->role() === Role::Superadmin) {
            throw new AuthenticationException('Superadmin accounts cannot be deleted from the admin panel.');
        }
        $statement = $this->database->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $user->id()]);
    }

    /** @param array<mixed> $row */
    private function hydrate(array $row): User
    {
        $id = $row['id'] ?? null;
        $email = $row['email'] ?? null;
        $hash = $row['password_hash'] ?? null;
        $role = $row['role'] ?? null;
        $enabled = $row['enabled'] ?? null;
        $handle = $row['webauthn_user_handle'] ?? null;
        if (!\is_int($id) || !\is_string($email) || !\is_string($hash) || !\is_string($role)
            || !\is_int($enabled) || !\is_string($handle)) {
            throw new AuthenticationException('Invalid user record.');
        }

        return new User($id, $email, $hash, Role::from($role), $enabled === 1, $handle);
    }

    private function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || \strlen($email) > 254) {
            throw new AuthenticationException('Email address is invalid.');
        }

        return $email;
    }
}
