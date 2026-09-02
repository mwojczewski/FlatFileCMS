# Production deployment

FlatFile CMS requires PHP 8.5+ and a document root pointing exactly at
`public/`. Do not expose the project root and do not copy content, config,
SQLite, logs or `.env.local` below `public/`.

## Release procedure

1. Deploy a clean checkout or release artifact containing `composer.lock`.
2. Run `composer install --no-dev --classmap-authoritative`.
3. Create `.env.local` from `.env.example` and use production values.
4. Set `config/setup.yml` `site.url` to the final HTTPS origin.
5. Run `php bin/cms database:migrate`.
6. Run the quality suite during build and `php bin/cms release:check` on the
   target host.
7. Point the web server to `public/`, then switch traffic to the release.
8. Verify `/api/v1/health`, one HTML page, one API page, login, upload and mail.

`release:check` exits non-zero if any required condition fails. It checks the
PHP runtime and extensions, production environment guard, writable roots,
configuration, all localized navigation, all page blocks, layouts, the SQLite
authentication schema and required deployment files.

## Required production environment

```dotenv
APP_ENV=production
APP_DEBUG=0
APP_SECRET=<at-least-32-random-bytes>
APP_TIMEZONE=Europe/Warsaw
SESSION_COOKIE_SECURE=1
SESSION_COOKIE_SAME_SITE=Lax
WEBAUTHN_RP_ID=example.com
```

The application refuses to bootstrap in production with debug enabled, a
placeholder/short secret or an insecure administrator cookie. Generate the
secret outside shell history, for example with a password manager or a system
cryptographic random generator.

## Filesystem permissions

The PHP user needs write access to:

- `pages/` and `config/` for CMS content;
- `storage/audit`, `cache`, `database`, `logs`, `sessions` and `tmp`;
- `public/assets/blocks/` for fingerprinted developer assets.

`app/`, `blocks/`, `templates/`, `vendor/`, `bin/` and PHP entrypoints should
be read-only for the web-server user. Do not make the whole project `0777`.
Runtime directories and roots must not be symlinks.

Application diagnostics are written as one JSON object per line to rotating
`storage/logs/application-YYYY-MM-DD.log` files. `LOG_LEVEL=notice` records
missing public routes as well as warnings and errors; `LOG_MAX_FILES` controls
retention. Logs can contain requested paths and client IP addresses and must
remain outside the public document root.

## Reverse proxies and Cloudflare

`TRUSTED_PROXIES` accepts a comma-separated list of exact IPs or IPv4/IPv6 CIDR
ranges. Only when the direct peer matches this list does the CMS use
`X-Forwarded-For` for audit and rate limiting. The chain is evaluated from the
nearest proxy toward the first untrusted client address.

Configure only the current ranges of proxies you actually use. Never use
`0.0.0.0/0` or `::/0`; that would let arbitrary clients spoof the address used
by login throttling. If Cloudflare is enabled, keep its published ranges in the
deployment environment and ensure the origin is not directly reachable or
also accepts direct-client behavior safely.

## Web server and PHP

Hardened examples for Nginx, Apache 2 and Caddy live in `deploy/`. They execute
only `public/index.php`, deny all other PHP paths, set baseline security headers
and enable negotiated compression for HTML and text assets. Adapt the domain,
project path, certificate paths and PHP-FPM socket before enabling a file.

The Nginx example enables gzip by default. Its optional Brotli directives need
the third-party `ngx_brotli` module. The Apache example uses `mod_deflate` and
documents how to switch to `mod_brotli`; enable the SSL, rewrite, headers,
proxy_fcgi, filter and compression modules used by the configuration. Caddy uses its
built-in Zstandard and gzip encoders and manages HTTPS certificates
automatically unless explicit certificate settings are added.

Apache deployments may alternatively use `public/.htaccess`, but the complete
virtual-host example is preferred because it does not depend on per-directory
overrides.

Set PHP `upload_max_filesize` and `post_max_size` at least as high as
`media.maxUploadBytes`, while retaining a server-level request limit. Disable
`display_errors`, keep `log_errors` enabled and protect server logs as sensitive
data.

## Backups and recovery

Back up these as one consistent release snapshot:

- `pages/`, `config/`, developer `blocks/` and `templates/`;
- `storage/database/cms.sqlite` while no write is active, or with SQLite's
  online backup mechanism;
- `.env.local` through a separate encrypted secret backup.

Caches, sessions, generated block assets and media variants are disposable.
Audit logs may be retained separately according to privacy and operational
policy. Test restoration periodically; a backup that has never been restored
is not a verified backup.

For rollback, restore both the compatible code release and its content/schema
snapshot. Database migrations are additive, but a rollback should still use a
tested backup. Run `release:check` again before returning traffic.
