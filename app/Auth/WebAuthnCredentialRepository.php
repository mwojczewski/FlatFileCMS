<?php

declare(strict_types=1);

namespace FlatFileCms\Auth;

use DateTimeImmutable;
use JsonException;
use PDO;

final readonly class WebAuthnCredentialRepository
{
    public function __construct(private PDO $database) {}

    /** @return list<WebAuthnCredential> */
    public function forUser(int $userId): array
    {
        $statement = $this->database->prepare('SELECT * FROM webauthn_credentials WHERE user_id = :user_id ORDER BY id');
        $statement->execute(['user_id' => $userId]);
        $credentials = [];
        foreach ($statement->fetchAll() as $row) {
            if (\is_array($row)) {
                $credentials[] = $this->hydrate($row);
            }
        }

        return $credentials;
    }

    public function findByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        $statement = $this->database->prepare('SELECT * FROM webauthn_credentials WHERE credential_id = :id');
        $statement->bindValue(':id', $credentialId, PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch();

        return \is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param list<string> $transports */
    public function add(
        User $user,
        string $name,
        string $credentialId,
        string $publicKey,
        int $signatureCounter,
        array $transports,
    ): void {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new AuthenticationException('Security key name must contain between 1 and 80 characters.');
        }
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO webauthn_credentials
    (user_id, name, credential_id, public_key, signature_counter, transports, created_at)
VALUES (:user_id, :name, :credential_id, :public_key, :signature_counter, :transports, :created_at)
SQL);
        $statement->bindValue(':user_id', $user->id(), PDO::PARAM_INT);
        $statement->bindValue(':name', $name);
        $statement->bindValue(':credential_id', $credentialId, PDO::PARAM_LOB);
        $statement->bindValue(':public_key', $publicKey, PDO::PARAM_LOB);
        $statement->bindValue(':signature_counter', $signatureCounter, PDO::PARAM_INT);
        $statement->bindValue(':transports', json_encode($transports, JSON_THROW_ON_ERROR));
        $statement->bindValue(':created_at', (new DateTimeImmutable())->format(DATE_ATOM));
        $statement->execute();
    }

    public function markUsed(WebAuthnCredential $credential, int $signatureCounter): void
    {
        $statement = $this->database->prepare(<<<'SQL'
UPDATE webauthn_credentials SET signature_counter = :counter, last_used_at = :used WHERE id = :id
SQL);
        $statement->execute([
            'counter' => $signatureCounter,
            'used' => (new DateTimeImmutable())->format(DATE_ATOM),
            'id' => $credential->id(),
        ]);
    }

    public function deleteForUser(int $credentialId, int $userId): void
    {
        $statement = $this->database->prepare('DELETE FROM webauthn_credentials WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $credentialId, 'user_id' => $userId]);
    }

    public function clearForUser(int $userId): int
    {
        $statement = $this->database->prepare('DELETE FROM webauthn_credentials WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount();
    }

    /** @param array<mixed> $row */
    private function hydrate(array $row): WebAuthnCredential
    {
        $transportsJson = $row['transports'] ?? null;
        if (!\is_string($transportsJson)) {
            throw new AuthenticationException('Invalid WebAuthn credential record.');
        }
        try {
            $transports = json_decode($transportsJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AuthenticationException('Invalid WebAuthn credential record.');
        }
        if (!\is_array($transports) || !array_is_list($transports)) {
            throw new AuthenticationException('Invalid WebAuthn transports.');
        }
        $normalizedTransports = [];
        foreach ($transports as $transport) {
            if (!\is_string($transport)) {
                throw new AuthenticationException('Invalid WebAuthn transport.');
            }
            $normalizedTransports[] = $transport;
        }

        return new WebAuthnCredential(
            $this->integerColumn($row, 'id'),
            $this->integerColumn($row, 'user_id'),
            $this->stringColumn($row, 'name'),
            $this->stringColumn($row, 'credential_id'),
            $this->stringColumn($row, 'public_key'),
            $this->integerColumn($row, 'signature_counter'),
            $normalizedTransports,
        );
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

        throw new AuthenticationException('Invalid WebAuthn credential record.');
    }

    /** @param array<mixed> $row */
    private function stringColumn(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!\is_string($value)) {
            throw new AuthenticationException('Invalid WebAuthn credential record.');
        }

        return $value;
    }
}
