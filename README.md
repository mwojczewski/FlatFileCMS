# FlatFile CMS

Reusable hybrid flat-file CMS for PHP 8.5+. The same domain model will serve normalized JSON through a REST API and render complete HTML through developer-defined PHP blocks and layouts.

Stages 0 and 1 are complete in this revision: architecture/contracts and the executable HTTP foundation.

## Current capabilities

- Composer and PSR-4 project structure;
- small shared-service container;
- environment loading from process variables and `.env.local`;
- immutable HTTP request and response objects;
- exact and `{parameter}` routes with GET/HEAD support;
- separate website, API and admin route spaces;
- central JSON/HTML exception rendering;
- production-safe internal errors;
- health endpoint;
- PHPUnit, PHPStan and PHP-CS-Fixer configuration;
- Apache and Nginx front-controller configuration examples.

Content repositories, YAML parsing, localized routes, rendering and the admin application intentionally begin in subsequent stages.

## Requirements

- PHP 8.5 or newer; PHP 8.4 and older are intentionally unsupported;
- Composer 2;
- extensions: Ctype, Filter, JSON, Mbstring, PDO and PDO SQLite;
- a web server whose document root can be set to `public/`, or equivalent rewrite protection on shared hosting.

## Installation for development

```bash
composer install
cp .env.example .env.local
composer check
php -S 127.0.0.1:8080 -t public public/index.php
```

Open `http://127.0.0.1:8080/`. Operational status is available from `GET /api/v1/health`.

Do not commit `.env.local`. Generate a unique `APP_SECRET` before a real deployment.
The included `.env.example` contains development-only values and documents the
planned session, authentication-throttling and mail settings. A production
installation must refuse the `CHANGE_ME` secret and insecure development
values.

## HTTP routes in stage 1

| Method | Route | Purpose |
|---|---|---|
| GET, HEAD | `/` | application readiness page |
| GET, HEAD | `/api/v1/health` | JSON operational health |
| GET, HEAD | `/admin` | protected-panel availability notice until auth is implemented |

Unknown API routes use the documented JSON error envelope. Unknown website routes receive an HTML error without local paths or a stack trace.

## Project map

```text
app/Core/           application kernel, environment and dependency wiring
app/Http/           transport request/response, router and error handling
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
- user content will be constrained to configured filesystem roots in stage 2;
- user and authentication state will be the only SQLite-backed domain in the completed CMS.

## Next stage

Stage 2 implements root-bound filesystem access, strict YAML parsing, atomic writes, locking, revision conflict detection and parsed-file caching. See `docs/ROADMAP.md` for the full sequence.
