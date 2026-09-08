<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Database;

use FlatFileCms\Support\UuidV7;
use PDO;

final readonly class SchemaInstaller
{
    public function __construct(private PDO $database) {}

    public function install(): void
    {
        $this->database->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    public_id TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL DEFAULT '',
    last_name TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('ROLE_ADMIN', 'ROLE_SUPERADMIN')),
    enabled INTEGER NOT NULL DEFAULT 1 CHECK (enabled IN (0, 1)),
    webauthn_user_handle BLOB NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    password_changed_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    credential_id BLOB NOT NULL UNIQUE,
    public_key BLOB NOT NULL,
    signature_counter INTEGER NOT NULL DEFAULT 0,
    transports TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL,
    last_used_at TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_webauthn_credentials_user ON webauthn_credentials(user_id);

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    action TEXT NOT NULL,
    identifier_hash TEXT NOT NULL,
    attempts INTEGER NOT NULL,
    window_started_at INTEGER NOT NULL,
    PRIMARY KEY (action, identifier_hash)
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    used_at INTEGER NULL
);

CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_user ON password_reset_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_expiry ON password_reset_tokens(expires_at);
SQL);
        $this->addUserProfileColumn('first_name');
        $this->addUserProfileColumn('last_name');
        $this->addPublicUserIds();
    }

    private function addUserProfileColumn(string $name): void
    {
        $statement = $this->database->query('PRAGMA table_info(users)');
        if ($statement === false) {
            throw new DatabaseException('Unable to inspect the users table.');
        }
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!\in_array($name, $columns, true)) {
            $this->database->exec("ALTER TABLE users ADD COLUMN {$name} TEXT NOT NULL DEFAULT ''");
        }
    }

    private function addPublicUserIds(): void
    {
        $statement = $this->database->query('PRAGMA table_info(users)');
        if ($statement === false) {
            throw new DatabaseException('Unable to inspect the users table.');
        }
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!\in_array('public_id', $columns, true)) {
            $this->database->exec("ALTER TABLE users ADD COLUMN public_id TEXT NOT NULL DEFAULT ''");
        }
        $missing = $this->database->query("SELECT id FROM users WHERE public_id = ''");
        if ($missing === false) {
            throw new DatabaseException('Unable to migrate public user identifiers.');
        }
        $update = $this->database->prepare('UPDATE users SET public_id = :public_id WHERE id = :id');
        foreach ($missing->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $update->execute(['public_id' => UuidV7::generate(), 'id' => $id]);
        }
        $this->database->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_public_id ON users(public_id)');
    }
}
