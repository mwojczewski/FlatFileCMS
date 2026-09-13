# Bootstrap performance audit

## Current request path

`public/index.php` loads Composer and `bootstrap/app.php`. The bootstrap reads the
environment, installs the production guard and logger, registers every service
factory, builds the router, and returns `Application`. Controller factories are
lazy and route handlers resolve controllers only after a route matches.

This is a sound baseline: admin, mail, media, database and analytics services are
not constructed for an ordinary public request. Registration of their service
closures still happens on every request. The router is now request-family scoped,
so it retains and compiles only public, API or admin route objects.

## Hot-path findings

1. Before this audit, constructing `ErrorHandler` during bootstrap eagerly
   resolved `WebErrorRenderer`. That transitively constructed the YAML stack,
   page repositories, block processing and rendering services. Constructors also
   performed `realpath()` checks and created cache/lock directories. Even the
   health endpoint paid this cost. Error responders are now supplied as lazy
   factories and are resolved only while rendering an error.
2. `Route::parameters()` rebuilt and quoted its regular expression on every
   attempted match. A public catch-all can be preceded by dozens of API and admin
   routes. Expressions and parameter names are now compiled once in the route
   constructor.
3. `YamlFileRepository::read()` always resolves the path, checks the file, reads
   all bytes and hashes them before consulting `YamlFileCache`. The cache avoids
   Symfony YAML parsing, but it does not avoid source I/O; JSON cache hits add a
   second file read and JSON decode. Serialized cache can reduce decode cost but
   has the same source-I/O characteristic.
4. `BlockRegistry::all()` remains available for admin discovery, but direct
   `get()` calls now load only the requested block. Public rendering therefore
   avoids enumerating and parsing unrelated block packages. Layout and partial
   registries use the same direct-lookup strategy.
5. `ContentFileIndex`, `PageRepository::all()` and
   `CollectionRepository::all()` recursively enumerate `pages/` when a route
   index is needed. The object-level index prevents duplicate scans inside one
   request, but PHP-FPM requests rebuild it.
6. `LayoutRegistry` and `PartialRegistry` enumerate their directories lazily per
   request. `AssetCollector` checks and timestamps block assets during rendering;
   `AssetPublisher` can then read/write them in the request path.
7. Composer already has `optimize-autoloader: true`. Production deployment should
   use `composer install --no-dev --classmap-authoritative` and OPcache with
   timestamp validation appropriate to the release strategy.
8. Public, admin and API requests share one bootstrap and one route table. Lazy
   controllers prevent the largest construction costs, but parsing service
   declarations and allocating unrelated route objects remains fixed overhead.

## Target fast-path

Split bootstrap into a tiny common kernel and request-family providers:

```text
entrypoint -> Request -> CommonKernel
                        |-- health: static response
                        |-- public: content + rendering provider
                        |-- API: content + serializers provider
                        `-- admin: auth + mutations + media provider
```

The entrypoint should create `Request` before selecting providers. Common setup
should contain only environment, production guard, logging, trusted proxies,
router and a lazy error-handler factory. Each provider should register only its
routes and services. Keep `/api/v1/health` independent of YAML, SQLite, sessions,
block discovery and custom error-page rendering.

## Compiled metadata

The release-scoped YAML cache is now available at
`storage/cache/compiled/yaml.php`. Enable it with
`COMPILED_CACHE_ENABLED=1` and build it with:

```shell
php bin/cms cache:warmup
```

Warmup is mandatory only when that switch is enabled. Run it after the complete
release has been copied and `APP_RELEASE` has been set, but before traffic is
switched to the release. A missing cache or an `APP_RELEASE` mismatch fails
closed on the first YAML read. YAML writes performed through the administration
layer rebuild the cache atomically. `cache:clear` intentionally removes it, so a
new warmup is required before serving traffic again in compiled mode.

Further compiled metadata can extend the same mechanism with:

- `blocks.php`: validated block definitions plus renderer/asset paths and source
  revisions;
- `content-index.php`: page and collection identities and route metadata;
- `templates.php`: layout and partial paths.

These should follow the existing PHP-array format, atomic writer and release
manifest. In development, leave compiled mode disabled for immediate visibility
of direct filesystem edits.

Do not change the existing YAML cache to trust only `mtime` and file size: rapid
or same-size edits can make such a cache stale. A deployment manifest (immutable
release id) or an explicit admin/CLI invalidation contract is required before
source reads can safely disappear from production requests.

## Recommended sequence

1. Keep the lazy error responders and precompiled route patterns in this change.
2. Extract the existing request-family route filtering into separate service
   providers, eliminating parsing of unrelated service definitions as well as
   route allocation.
3. Extend the existing warmup manifest with blocks and template/content indexes.
4. Add a deployment check that asserts compiled mode has been warmed before the
   release is activated.
5. Benchmark cold and warm public, API, admin-login and error responses under the
   actual PHP-FPM/OPcache configuration. Track wall time, peak memory, file opens,
   stats and YAML parser calls.
