<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use PDO;

final readonly class RateLimiter
{
    public function __construct(
        private PDO $database,
        private string $secret,
        private int $maxAttempts,
        private int $windowSeconds,
    ) {}

    public function assertAllowed(string $action, string $identifier): void
    {
        $row = $this->row($action, $identifier);
        if ($row !== null && $row['window_started_at'] + $this->windowSeconds > time()
            && $row['attempts'] >= $this->maxAttempts) {
            throw new AuthenticationException('Too many authentication attempts. Try again later.');
        }
    }

    public function hit(string $action, string $identifier): void
    {
        $hash = $this->hash($identifier);
        $now = time();
        $this->database->beginTransaction();
        try {
            $row = $this->rowByHash($action, $hash);
            if ($row === null || $row['window_started_at'] + $this->windowSeconds <= $now) {
                $statement = $this->database->prepare(<<<'SQL'
INSERT INTO auth_rate_limits (action, identifier_hash, attempts, window_started_at)
VALUES (:action, :hash, 1, :started)
ON CONFLICT(action, identifier_hash) DO UPDATE SET attempts = 1, window_started_at = excluded.window_started_at
SQL);
                $statement->execute(['action' => $action, 'hash' => $hash, 'started' => $now]);
            } else {
                $statement = $this->database->prepare(<<<'SQL'
UPDATE auth_rate_limits SET attempts = attempts + 1 WHERE action = :action AND identifier_hash = :hash
SQL);
                $statement->execute(['action' => $action, 'hash' => $hash]);
            }
            $this->database->commit();
        } catch (\Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }

    public function clear(string $action, string $identifier): void
    {
        $statement = $this->database->prepare('DELETE FROM auth_rate_limits WHERE action = :action AND identifier_hash = :hash');
        $statement->execute(['action' => $action, 'hash' => $this->hash($identifier)]);
    }

    /** @return array{attempts: int, window_started_at: int}|null */
    private function row(string $action, string $identifier): ?array
    {
        return $this->rowByHash($action, $this->hash($identifier));
    }

    /** @return array{attempts: int, window_started_at: int}|null */
    private function rowByHash(string $action, string $hash): ?array
    {
        $statement = $this->database->prepare(<<<'SQL'
SELECT attempts, window_started_at FROM auth_rate_limits WHERE action = :action AND identifier_hash = :hash
SQL);
        $statement->execute(['action' => $action, 'hash' => $hash]);
        $row = $statement->fetch();
        if (!\is_array($row)) {
            return null;
        }

        return [
            'attempts' => $this->integerColumn($row, 'attempts'),
            'window_started_at' => $this->integerColumn($row, 'window_started_at'),
        ];
    }

    /** @param array<mixed> $row */
    private function integerColumn(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);
            if (\is_int($parsed)) {
                return $parsed;
            }
        }

        throw new AuthenticationException('Invalid authentication rate-limit record.');
    }

    private function hash(string $identifier): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($identifier)), $this->secret);
    }
}
