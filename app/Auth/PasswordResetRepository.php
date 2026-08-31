<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use PDO;

final readonly class PasswordResetRepository
{
    public function __construct(
        private PDO $database,
        private UserRepository $users,
    ) {}

    public function issue(User $user, string $tokenHash, int $expiresAt): void
    {
        $this->database->beginTransaction();
        try {
            $this->revokeForUser($user->id());
            $statement = $this->database->prepare(<<<'SQL'
INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
VALUES (:user_id, :token_hash, :expires_at, :created_at)
SQL);
            $statement->execute([
                'user_id' => $user->id(),
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_at' => time(),
            ]);
            $this->database->commit();
        } catch (\Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }

    public function valid(string $tokenHash, int $now): bool
    {
        $statement = $this->database->prepare(<<<'SQL'
SELECT 1 FROM password_reset_tokens
WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at >= :now
SQL);
        $statement->execute(['token_hash' => $tokenHash, 'now' => $now]);

        return $statement->fetchColumn() !== false;
    }

    public function claim(string $tokenHash, int $now): User
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(<<<'SQL'
SELECT user_id FROM password_reset_tokens
WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at >= :now
SQL);
            $statement->execute(['token_hash' => $tokenHash, 'now' => $now]);
            $userId = $statement->fetchColumn();
            if (!\is_int($userId) && !\is_string($userId)) {
                throw new AuthenticationException('Password reset link is invalid or expired.');
            }

            $update = $this->database->prepare(<<<'SQL'
UPDATE password_reset_tokens SET used_at = :used_at
WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at >= :now
SQL);
            $update->execute(['used_at' => $now, 'token_hash' => $tokenHash, 'now' => $now]);
            if ($update->rowCount() !== 1) {
                throw new AuthenticationException('Password reset link is invalid or expired.');
            }
            $this->database->commit();

            return $this->users->get((int) $userId);
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function revokeForUser(int $userId): void
    {
        $statement = $this->database->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
    }
}
