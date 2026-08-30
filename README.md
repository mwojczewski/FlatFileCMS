# FlatFile CMS

Reusable hybrid flat-file CMS for PHP 8.5+. The same domain model will serve normalized JSON through a REST API and render complete HTML through developer-defined PHP blocks and layouts.

Stages 0–6 are complete in this revision: architecture/contracts, the executable
HTTP foundation, safe filesystem/YAML access and the localized public content
API with schema-validated blocks, server-side HTML rendering and collections.

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
- Apache and Nginx front-controller configuration examples;
- portable relative-path and page-identity value objects;
- root-bound filesystem resolution with symlink-escape protection;
- exclusive file locking and atomic replacement;
- SHA-256 revision conflict detection;
- restrictive, bounded YAML parsing;
- content-hash-invalidated parsed YAML cache;
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

The admin application intentionally begins in a subsequent stage.

## Requirements

- PHP 8.5 or newer; PHP 8.4 and older are intentionally unsupported;
- Composer 2;
- extensions: Ctype, Filter, JSON, Mbstring, PDO and PDO SQLite;
- a web server whose document root can be set to `public/`, or equivalent rewrite protection on shared hosting.

The production dependencies are deliberately narrow. `symfony/yaml` provides a
mature restrictive YAML parser without pulling in Symfony Framework;
`league/commonmark` provides standards-compliant Markdown rendering with raw
HTML stripping and unsafe-link protection. Implementing either parser in the
CMS core would increase security risk without adding product value.

## Installation for development

```bash
composer install
cp .env.example .env.local
composer check
php -S 127.0.0.1:8080 -t public public/index.php
```

Open `http://127.0.0.1:8080/`. Operational status is available from `GET /api/v1/health`.

Create a developer block package with:

```bash
php bin/cms block:create image-with-text
php bin/cms block:create gallery-slider --with-assets
```

The first form creates the required `block.yml` and `render.php`. The optional
flag also creates scoped `style.css` and `script.js`. Existing block directories
are never overwritten.

Do not commit `.env.local`. Generate a unique `APP_SECRET` before a real deployment.
The included `.env.example` contains development-only values and documents the
planned session, authentication-throttling and mail settings. A production
installation must refuse the `CHANGE_ME` secret and insecure development
values.

Configuration has one owner per concern: deployment/runtime values and secrets
belong to `.env.local`; site URL, SEO, layouts and media behavior belong to
`config/setup.yml`. See [docs/CONFIGURATION.md](docs/CONFIGURATION.md) for the
complete boundary. `YAML_CACHE_ENABLED` controls only the infrastructure cache
of parsed YAML and is intentionally not a site setting.

## HTTP routes through stage 6

| Method | Route | Purpose |
|---|---|---|
| GET, HEAD | `/` | server-rendered homepage; redirects to the default locale in multilingual mode |
| GET, HEAD | `/api/v1/health` | JSON operational health |
| GET, HEAD | `/api/v1/pages?lang=pl` | localized homepage data |
| GET, HEAD | `/api/v1/pages/{path*}?lang=pl` | localized page data resolved from public slugs |
| GET, HEAD | `/api/v1/navigation?lang=pl` | localized navigation with resolved page links |
| GET, HEAD | `/api/v1/config?lang=pl` | deliberate public configuration projection |
| GET, HEAD | `/api/v1/collections/{path*}?lang=pl&page=1` | localized, filtered and paginated collection |
| GET, HEAD | `/admin` | protected-panel availability notice until auth is implemented |
| GET, HEAD | `/{path*}` | server-rendered website page or collection; locale-prefixed in multilingual mode |

Unknown API routes use the documented JSON error envelope. Unknown website routes receive an HTML error without local paths or a stack trace.

## Project map

```text
app/Core/           application kernel, environment and dependency wiring
app/Http/           transport request/response, router and error handling
app/Collections/    collection definitions, queries, filtering and pagination
app/Domain/         transport-independent content value objects
app/Infrastructure/ safe filesystem and YAML implementations
blocks/             developer-defined block packages
config/             editable non-secret YAML configuration and PHP routes
docs/               contracts and delivery roadmap
pages/              content tree
public/             only public document root
storage/            runtime data
templates/          developer-defined layouts and partials
tests/              unit and feature tests
```

See [ARCHITECTURE.md](ARCHITECTURE.md) for accepted decisions and [docs/CONTENT-CONTRACTS.md](docs/CONTENT-CONTRACTS.md) for the first on-disk contract.
Filesystem guarantees and cache behavior are documented in [docs/FILESYSTEM-SAFETY.md](docs/FILESYSTEM-SAFETY.md).
Configuration ownership is documented in [docs/CONFIGURATION.md](docs/CONFIGURATION.md).
Block packages, field rules and extension points are documented in [docs/BLOCKS.md](docs/BLOCKS.md).
Server-side rendering and template boundaries are documented in [docs/RENDERING.md](docs/RENDERING.md).
Collection contracts and queries are documented in [docs/COLLECTIONS.md](docs/COLLECTIONS.md).

## Web-server setup

### Apache

Point the virtual-host document root at `public/` and allow overrides for its `.htaccess`. If a hosting provider forces the project into the web root, explicitly deny HTTP access to every directory except `public/`; using a real `public/` document root remains strongly preferred.

### Nginx

Adapt `deploy/nginx.conf.example`, especially the project path and PHP-FPM socket.

## Quality commands

```bash
composer test
composer analyse
composer fix-style
composer check-style
composer check
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

## Next stage

Stage 7 introduces SQLite users, the CLI installer and role-aware user
management. See `docs/ROADMAP.md` for the full sequence.
