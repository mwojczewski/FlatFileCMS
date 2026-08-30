# Authentication and security keys

Administrator identities and authentication mechanisms are the only CMS data
stored in SQLite. Content remains exclusively file-backed.

## Installation

Configure `.env.local`, especially a random `APP_SECRET`, session cookie flags
and the WebAuthn relying-party identity. Then create the first technical
superadmin:

```bash
php bin/cms install root@example.com
```

Passwords are read without placing them in process arguments. For controlled
non-interactive installation, provide `CMS_PASSWORD` only to that process.

## Users and roles

```bash
php bin/cms user:create admin@example.com
php bin/cms user:create-superadmin service@example.com
php bin/cms user:password admin@example.com
```

Only CLI installation and `user:create-superadmin` can create a superadmin.
Repository queries hide every superadmin from an admin, including direct ID
lookups.

## YubiKey / WebAuthn

The account security page at `/admin/security` can register multiple roaming
WebAuthn credentials such as YubiKey over USB, NFC or BLE. Registration requires
an authenticated session and the current password. The private key never
leaves the authenticator; SQLite stores the credential ID, public key,
signature counter and declared transports.

Adding the first key enables mandatory second-factor verification for that
account. Accounts without a registered key continue to use password-only
login. If every key is lost, a server operator can restore password-only login:

```bash
php bin/cms user:security-keys:clear admin@example.com
```

## Changing the current password

An authenticated user can open `/admin/account/password` from the account
screen. The operation requires the current password, a different new password
that satisfies the backend policy, matching confirmation and a valid CSRF
token.

After the database update the user remains logged in, but the session
identifier is regenerated. Every authenticated admin screen exposes a
CSRF-protected POST logout button in its navigation bar.

`WEBAUTHN_RP_ID` is the exact hostname without scheme, port or path. Credentials
are cryptographically bound to it. Production requires HTTPS; browsers allow
plain HTTP only for localhost development.

The implementation intentionally requests no authenticator attestation. It
therefore accepts compatible roaming FIDO2/U2F security keys rather than
tracking or enforcing a specific YubiKey model.
