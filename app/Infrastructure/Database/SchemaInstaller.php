<?php

declare(strict_types=1);

namespace FlatFileCms\Infrastructure\Database;

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
SQL);
    }
}
