# FlatFile CMS

Reusable hybrid flat-file CMS for PHP 8.5+. The same domain model will serve normalized JSON through a REST API and render complete HTML through developer-defined PHP blocks and layouts.

Stages 0–13 are complete in this revision: architecture/contracts, the executable
HTTP foundation, safe filesystem/YAML access and the localized public content
API with schema-validated blocks, server-side HTML rendering, collections and
the administrator authentication boundary, filesystem-backed page CRUD and the
schema-driven page builder, unified responsive admin UI, password recovery,
SMTP delivery, a filesystem audit trail, secure page-local media management,
revision-safe configuration editors and production release hardening.

## Current capabilities

- Composer and PSR-4 project structure;
- small shared-service container;
- environment loading from process variables and `.env.local`;
- immutable HTTP request and response objects;
- exact, `{parameter}` and final `{path*}` routes with GET/HEAD support;
- separate website, API and admin route spaces;
- central JSON/HTML exception rendering;
- production-safe internal errors;
- health endpoint;
- PHPUnit, PHPStan and PHP-CS-Fixer configuration;
- Apache, Nginx and Caddy front-controller configuration examples;
- portable relative-path and page-identity value objects;
- root-bound filesystem resolution with symlink-escape protection;
- exclusive file locking and atomic replacement;
- SHA-256 revision conflict detection;
- restrictive, bounded YAML parsing;
- content-hash-invalidated parsed YAML caches using independently switchable JSON and PHP serialization;
- validated page, language, configuration and navigation repositories;
- localized public route index with sibling-collision detection;
- central SEO fallback resolution;
- normalized page, navigation and public-configuration API responses;
- ETag and Last-Modified conditional requests;
- automatic developer block discovery from `blocks/*/block.yml`;
- extensible field-type registry with all standard CMS field types;
- schema-aware block validation, normalization and localization;
- UUID v7 uniqueness and page-local media-reference validation;
- shared page view models for the JSON and HTML adapters;
- safe Markdown, block, partial, layout and page renderers;
- multilingual website routing with default-locale redirects;
- per-page block asset discovery, deduplication and content fingerprinting;
- cache validators for both JSON and HTML responses;
- `pagination.yml` collections with translated routes, sorting, filtering and pagination;
- collection output through both REST API and server-rendered layouts.
- SQLite users with strict admin/superadmin visibility rules;
- password login, secure sessions, CSRF and persistent login throttling;
- authenticated password changes with current-password verification and session-ID rotation;
- optional YubiKey/WebAuthn second factor with multi-key support and CLI recovery.
- authenticated page tree and metadata/SEO editor;
- safe page creation, subtree moves and recursive deletion;
- optimistic revision checks and localized-route collision prevention on every page write.
- block picker generated automatically from developer `block.yml` definitions;
- schema-driven multilingual block forms covering every built-in field type and repeaters;
- revision-safe add, edit, duplicate, enable, reorder and delete block operations.
- local EasyMDE editor for every schema-defined Markdown field, including fields added dynamically in repeaters;
- unified responsive admin shell shared by the dashboard, pages, account and authentication screens;
- enumeration-safe, rate-limited, one-time password reset with hashed SQLite tokens;
- SMTP mail delivery through PHPMailer;
- daily, locked JSONL audit logs stored under `storage/audit/`.
- page-local media browsing, upload, selection and reference-safe deletion;
- actual-MIME verification, SVG sanitization, pixel limits and optional metadata removal;
- immutable fingerprinted media URLs with ETag, byte ranges and headless response metadata;
- bounded GD image variants with independently switchable transformations and cache.
- multilingual navigation editor with stable page/collection references and bounded nesting;
- allowlisted global site, SEO and media configuration editor preserving custom keys;
- trusted-proxy-aware client IP resolution for throttling and audit;
- fail-closed production runtime and executable deployment preflight.
- dedicated PHP templates under `templates/admin/` instead of controller-built page markup;
- automatic canonical suggestion for new pages based on `site.url`, locale routing and the default-language slug;
- revision-safe collection settings editor for localized metadata, SEO, sorting, pagination and filters;
- administrator CRUD with backend-enforced superadmin invisibility and technical-account protection;
- separate password and WebAuthn/U2F account panels with per-key removal;
- separate public typography and frontend stylesheets shared by the homepage and subpages.
- developer-controlled responsive image variants with contain/cover fitting and `<picture>` output;
- automatically generated multilingual `sitemap.xml` containing only enabled content;
- editable `llms.txt` and `security.txt` public text files;
- filesystem-backed 301/302/303/307/308 redirect rules with loop prevention;
- rotating Monolog JSON diagnostics for runtime failures and missing public routes;
- EasyMDE page-media picker and an in-panel fullscreen image preview.

## Requirements

- PHP 8.5 or newer; PHP 8.4 and older are intentionally unsupported;
- Composer 2;
- extensions: Ctype, DOM, Fileinfo, Filter, GD, JSON, Mbstring, OpenSSL, PDO and PDO SQLite;
- a web server whose document root can be set to `public/`, or equivalent rewrite protection on shared hosting.

The production dependencies are deliberately narrow. `symfony/yaml` provides a
mature restrictive YAML parser without pulling in Symfony Framework;
`league/commonmark` provides standards-compliant Markdown rendering with raw
HTML stripping and unsafe-link protection; `phpmailer/phpmailer` provides
maintained SMTP transport and TLS handling; `monolog/monolog` provides PSR-3
logging and bounded file rotation. Implementing these protocols and
parsers in CMS core would increase security risk without adding product value.

## Installation for development

```bash
composer install
cp .env.example .env.local
composer check
php -S localhost:8080 -t public public/router.php
```

Open `http://localhost:8080/`. Operational status is available from `GET /api/v1/health`.

Install the authentication database and first technical superadmin:

```bash
php bin/cms install root@example.com
```

For an installation created before stage 10, apply the additive authentication
schema migration once:

```bash
php bin/cms database:migrate
```

Create a developer block package with:

```bash
php bin/cms block:create image-with-text
php bin/cms block:create gallery-slider --with-assets
php bin/cms cache:clear
php bin/cms cache:prune --dry-run
php bin/cms cache:prune
php bin/cms runtime:prune --dry-run
php bin/cms runtime:prune
```

The first form creates the required `block.yml` and `render.php`. The optional
flag also creates scoped `style.css` and `script.js`. Existing block directories
are never overwritten.

Do not commit `.env.local`. Generate a unique `APP_SECRET` before a real deployment.
The included `.env.example` contains development-only values and documents the
planned session, authentication-throttling and mail settings. Production
bootstrap rejects placeholder secrets, debug mode and insecure session cookies.

Configuration has one owner per concern: deployment/runtime values and secrets
belong to `.env.local`; site URL, SEO, layouts and media behavior belong to
`config/setup.yml`. See [docs/CONFIGURATION.md](docs/CONFIGURATION.md) for the
complete boundary. `YAML_CACHE_JSON_ENABLED` controls the JSON infrastructure cache
of parsed YAML, while `YAML_CACHE_SERIALIZE_ENABLED` independently controls the
PHP-serialized representation. They may be enabled simultaneously and are
intentionally not site settings.

## HTTP routes through stage 13

| Method | Route | Purpose |
|---|---|---|
| GET, HEAD | `/` | server-rendered homepage; redirects to the default locale in multilingual mode |
| GET, HEAD | `/api/v1/health` | JSON operational health |
| GET, HEAD | `/api/v1/pages?lang=pl` | localized homepage data |
| GET, HEAD | `/api/v1/pages/{path*}?lang=pl` | localized page data resolved from public slugs |
| GET, HEAD | `/api/v1/navigation?lang=pl` | localized navigation with resolved page links |
| GET, HEAD | `/api/v1/config?lang=pl` | deliberate public configuration projection |
| GET, HEAD | `/api/v1/collections/{path*}?lang=pl&page=1` | localized, filtered and paginated collection |
| GET, HEAD | `/media/{technical-page-id}/{fingerprint}/{filename}` | immutable original or bounded image variant selected with `w`, `h`, `format`, `fit` |
| GET, HEAD | `/sitemap.xml` | generated enabled page and collection URLs for every locale |
| GET, HEAD | `/llms.txt`, `/lms.txt` | administrator-supplied LLM description; second route is an alias |
| GET, HEAD | `/security.txt`, `/.well-known/security.txt` | administrator-supplied security contact policy |
| GET, HEAD | `/admin/login` | administrator password login |
| GET, POST | `/admin/password/forgot` | enumeration-safe password-reset request |
| GET, POST | `/admin/password/reset` | validate a one-time token and set a new password |
| GET, HEAD | `/admin` | authenticated panel entry |
| GET, HEAD | `/admin/security` | account security overview |
| GET, POST | `/admin/account/password` | change the authenticated account password |
| GET | `/admin/account/security-keys` | list and register YubiKey/WebAuthn credentials |
| POST | `/admin/account/security-keys/delete` | delete one credential belonging to the authenticated account |
| GET, POST | `/admin/users`, `/admin/users/*` | role-safe CRUD for administrator accounts |
| GET, HEAD | `/admin/pages` | authenticated page tree |
| GET, POST | `/admin/pages/create` | create a filesystem-backed page |
| GET | `/admin/pages/edit?path=…` | edit page metadata and SEO |
| POST | `/admin/pages/update` | revision-safe page update |
| POST | `/admin/pages/move` | atomically move a page subtree |
| POST | `/admin/pages/delete` | permanently delete a page subtree |
| GET, POST | `/admin/collections/edit`, `/admin/collections/update` | revision-safe collection settings editor |
| GET | `/admin/pages/builder?path=…` | manage the ordered block list of a page |
| GET | `/admin/pages/builder/picker?path=…` | choose a discovered block type |
| GET, POST | `/admin/pages/builder/create` | render a schema form and add a block |
| GET | `/admin/pages/builder/edit?path=…&id=…` | edit one block through its generated form |
| POST | `/admin/pages/builder/update` | revision-safe block data update |
| POST | `/admin/pages/builder/duplicate` | duplicate a block with a new UUID v7 |
| POST | `/admin/pages/builder/toggle` | enable or disable a block |
| POST | `/admin/pages/builder/reorder` | persist the complete drag-and-drop order |
| POST | `/admin/pages/builder/delete` | permanently remove one block |
| GET | `/admin/media?path=…` | browse and upload page-local media |
| GET | `/admin/media/picker?path=…&kind=image` | authenticated JSON media picker |
| POST | `/admin/media/upload` | validate and atomically store one upload |
| POST | `/admin/media/delete` | remove one unreferenced media file |
| GET, POST | `/admin/navigation` | revision-safe localized menu editor |
| GET, POST | `/admin/settings` | allowlisted global site, SEO and media configuration |
| POST | `/admin/settings/llms`, `/admin/settings/security-text` | revision-safe public text-file editors |
| GET, POST | `/admin/redirects`, `/admin/redirects/*` | revision-safe redirect-rule CRUD |
| GET, HEAD | `/{path*}` | server-rendered website page or collection; locale-prefixed in multilingual mode |

Unknown API routes use the documented JSON error envelope. Unknown website routes receive an HTML error without local paths or a stack trace.

## Project map

```text
app/Core/           application kernel, environment and dependency wiring
app/Http/           transport request/response, router and error handling
app/Collections/    collection definitions, queries, filtering and pagination
app/Domain/         transport-independent content value objects
app/Infrastructure/ safe filesystem and YAML implementations
app/Media/          page-local files, validation, variants and public delivery
app/Redirects/      filesystem redirect rules and public resolution
app/Logging/        Monolog setup and PHP runtime diagnostics
blocks/             developer-defined block packages
config/             editable non-secret YAML configuration and PHP routes
docs/               contracts and delivery roadmap
pages/              content tree
public/             only public document root
storage/            runtime data
templates/          public layouts/partials and isolated admin-panel views
tests/              unit and feature tests
```

See [ARCHITECTURE.md](ARCHITECTURE.md) for accepted decisions and [docs/CONTENT-CONTRACTS.md](docs/CONTENT-CONTRACTS.md) for the first on-disk contract.
Filesystem guarantees and cache behavior are documented in [docs/FILESYSTEM-SAFETY.md](docs/FILESYSTEM-SAFETY.md).
Configuration ownership is documented in [docs/CONFIGURATION.md](docs/CONFIGURATION.md).
Block packages, field rules and extension points are documented in [docs/BLOCKS.md](docs/BLOCKS.md).
Server-side rendering and template boundaries are documented in [docs/RENDERING.md](docs/RENDERING.md).
Collection contracts and queries are documented in [docs/COLLECTIONS.md](docs/COLLECTIONS.md).
Authentication, roles and YubiKey recovery are documented in [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md).
SMTP password recovery is covered by the same authentication guide, while the
filesystem event format and retention boundary are documented in [docs/AUDIT-LOG.md](docs/AUDIT-LOG.md).
Page administration and destructive-operation rules are documented in [docs/PAGE-ADMIN.md](docs/PAGE-ADMIN.md).
Dynamic block forms and concurrency guarantees are documented in [docs/PAGE-BUILDER.md](docs/PAGE-BUILDER.md).
Media security, headless output and image variants are documented in [docs/MEDIA.md](docs/MEDIA.md).
Navigation and global editor boundaries are documented in [docs/SETTINGS-ADMIN.md](docs/SETTINGS-ADMIN.md).
Production preflight, permissions, proxy handling, backup and rollback are documented in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Web-server setup

### Apache

Adapt `deploy/apache2-vhost.conf.example`, especially the domain, project path,
certificate paths and PHP-FPM socket. Shared hosting can instead use
`public/.htaccess`, but a real `public/` document root remains strongly preferred.

### Nginx

Adapt `deploy/nginx.conf.example`, especially the project path and PHP-FPM socket.

### Caddy

Adapt `deploy/Caddyfile.example`, especially the domain, project path and
PHP-FPM socket. Caddy provisions HTTPS automatically and the example enables
negotiated Zstandard/gzip compression.

## Quality commands

```bash
composer test
composer analyse
composer fix-style
composer check-style
composer check
php bin/cms release:check
```

Run `composer fix-style` before `composer check` when PHP CS Fixer reports a
format-only diff.

The supported source style is PER-CS 2.0 with `strict_types=1` in every PHP source file.

Deprecation notices are defects. The project does not use deprecated PHP APIs,
does not add compatibility shims for obsolete PHP releases and does not accept
dependencies that emit deprecation notices on the supported PHP 8.5 runtime.
The test bootstrap converts `E_DEPRECATED` and `E_USER_DEPRECATED` notices into
test failures.

## Security baseline

- only `public/` is web-accessible;
- secrets belong to environment variables, never YAML;
- production errors hide stack traces and filesystem paths;
- executable block/layout PHP is trusted developer code and will never be editable in the admin panel;
- user-controlled paths are constrained to configured filesystem roots;
- user and authentication state will be the only SQLite-backed domain in the completed CMS.

## Filesystem and YAML API

Application services receive `FilesystemRoot` and `RelativePath`, never an
unchecked absolute path. YAML reads return a `YamlDocument` containing both the
normalized mapping and its `FileRevision`. Pass that revision back to `write()`
to prevent a stale editor from overwriting newer content. New files use
`FileRevision::missing()`.

## Public content API

The `lang` query parameter defaults to the configured default locale. Disabled
pages return the same `PAGE_NOT_FOUND` response as missing pages. Public page
paths use translated slugs, while the `id` in the response remains the stable
technical directory identity. In multilingual mode generated website URLs are
prefixed with the locale; single-language installations produce unprefixed
URLs.

Every successful public API response includes `ETag`, `Last-Modified` and
`Cache-Control: public, max-age=0, must-revalidate`. Both `If-None-Match` and
`If-Modified-Since` are supported. See [docs/API.md](docs/API.md) for response
contracts and examples.

## Release baseline

The planned stages 0–13 are complete. New work can now be versioned as product
features rather than unfinished foundation. Run `composer check` during build
and `php bin/cms release:check` with the final production environment on every
deployment.
