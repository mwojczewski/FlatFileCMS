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

`/admin/users` provides CRUD for regular administrator accounts. Admins and
superadmins can create, edit, enable, disable and delete `ROLE_ADMIN` accounts.
The backend never permits creating or modifying a superadmin through HTTP,
prevents self-disable/self-delete, and makes an admin's direct request for a
superadmin behave like a missing user. A superadmin is shown only to another
superadmin as a technical, CLI-managed account.

## YubiKey / WebAuthn

The dedicated page at `/admin/account/security-keys` can register multiple roaming
WebAuthn credentials such as YubiKey over USB, NFC or BLE. Registration requires
an authenticated session and the current password. The private key never
leaves the authenticator; SQLite stores the credential ID, public key,
signature counter and declared transports.

Each credential can be removed independently. The delete query is scoped to
the authenticated user's ID, so a forged credential ID cannot remove another
account's key.

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

## Password recovery

The unauthenticated `/admin/password/forgot` form always returns the same
outcome regardless of whether an enabled account exists. Requests are throttled
independently by source IP and normalized email address. A successful request
creates 32 random bytes, sends only the URL-safe token by email and stores only
its SHA-256 digest in SQLite.

The token expires after `AUTH_PASSWORD_RESET_TTL`, can be claimed only once and
is invalidated together with every other reset token for that user after a
successful password change. A failed SMTP delivery revokes the newly created
token. Configure `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, credentials and
the sender identity in `.env.local`; accepted encryption values are `none`,
`starttls` and `smtps`.

Existing installations must add the token table before enabling the route:

```bash
php bin/cms database:migrate
```

Password-reset requests and completions are written to the filesystem audit
trail. The mail body uses `site.url` from `config/setup.yml` as the canonical
base URL, keeping the public site identity outside the secret environment.

`WEBAUTHN_RP_ID` is the exact hostname without scheme, port or path. Credentials
are cryptographically bound to it. Production requires HTTPS; browsers allow
plain HTTP only for localhost development.

The implementation intentionally requests no authenticator attestation. It
therefore accepts compatible roaming FIDO2/U2F security keys rather than
tracking or enforcing a specific YubiKey model.
