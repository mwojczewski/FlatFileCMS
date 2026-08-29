# FlatFile CMS — Architecture Decision Record

Status: accepted for stages 0–1  
Baseline: PHP 8.5+

The project intentionally provides no compatibility layer for PHP 8.4 or older.
New code must use the PHP 8.5 language and runtime baseline directly.

## 1. Product boundary

FlatFile CMS is a reusable hybrid CMS with two equal delivery adapters:

1. Headless: filesystem → domain/application layer → JSON API.
2. Server-side: filesystem → domain/application layer → block and layout renderers → HTML.

Neither adapter calls the other. Content, navigation, public configuration, SEO and media metadata live only in files. SQLite is limited to users and mechanisms directly supporting authentication, including password-reset tokens and login throttling.

## 2. Trust boundaries

- Administrator-controlled: `pages/`, editable values in `config/`, and page media.
- Developer-controlled: `blocks/`, executable renderers, layouts, partials and frontend assets.
- Secret-controlled: environment variables or an untracked `.env.local` file.
- Runtime-controlled: `storage/`.

Block and template PHP is trusted developer code. `RenderContext` deliberately limits the supported API but is not a security sandbox for malicious PHP.

## 3. Dependency direction

```text
HTTP / CLI / render adapters
             ↓
        Application
             ↓
           Domain
             ↑
 Filesystem / SQLite / Mail / Cache
```

Domain code does not depend on HTTP, YAML, PDO, sessions or templates. Controllers translate transport input and output and contain no content rules.

## 4. Stable page identity and localized URLs

The relative page directory is its stable internal identifier. Directory names are technical ASCII slugs and do not determine public URLs.

Example identity: `services/websites`.

Each `content.yml` contains localized public slugs. For multiple languages, the public routes can therefore be `/pl/oferta/strony-www` and `/en/services/websites` while both resolve to the same directory.

`pages/homepage/content.yml` is reserved for the home page and maps to `/` in single-language mode or `/{locale}/` in multilingual mode.

When more than one language is active, unprefixed public routes redirect to the default locale. Initial redirects use HTTP 302 so language configuration can change safely.

## 5. Users and authorization

Roles are `ROLE_ADMIN` and `ROLE_SUPERADMIN`.

Admins can list, create, edit and delete other admins, but cannot delete themselves. Superadmins are completely invisible to admins at repository/query and authorization levels. A direct request for a superadmin behaves exactly like a missing user.

Superadmins are technical recovery/service accounts. They are created only through installation or `php bin/cms user:create-superadmin`; the admin UI and ordinary `user:create` command cannot assign that role. Superadmins cannot be deleted through the panel.

## 6. Write model

There is no draft/published workflow or version history. A valid write becomes public immediately. `enabled: false` removes a page from public API and HTML routing while retaining it in the admin panel.

YAML writes use a temporary file in the destination filesystem, validation, an exclusive lock and atomic rename. A revision/content hash prevents silent lost updates. Deleting a page physically removes its directory after dependency checks and explicit confirmation.

## 7. HTTP and errors

The public document root is `public/`. API, admin and website routes share one HTTP kernel but separate route groups. Production errors never expose stack traces or local paths.

API failures use:

```json
{
  "error": {
    "code": "PAGE_NOT_FOUND",
    "message": "Page not found"
  }
}
```

Stage 1 implements the transport foundation. Content-aware errors will add stable domain codes in later stages.

## 8. Libraries

- `symfony/yaml`: mature, safe YAML parsing without adopting Symfony Framework.
- PHPUnit: executable regression tests.
- PHPStan: maximum-level static analysis.
- PHP-CS-Fixer: deterministic PER-CS 2.0 formatting.

Only dependency releases actively compatible with PHP 8.5 may be introduced.
The project does not knowingly accept dependencies or internal code that call
APIs marked as deprecated by PHP 8.5 or by the dependency's supported public
contract. Deprecation notices are treated as test-suite failures. Removing a
deprecated call takes precedence over suppressing its notice or adding a
compatibility shim.

Routing is intentionally implemented by a small local exact/dynamic router. Its required feature set is limited, and keeping it local avoids wrapping a third-party router with an equally large integration layer. New dependencies will be added only with a recorded concrete use.

## 9. Deployment targets

The application must support Apache, Nginx, Docker and shared PHP hosting. Production should point the document root at `public/`. Runtime directories must be writable by PHP; source and developer code should not be writable by the web-server user except where deployment constraints require it.

## 10. Stage boundaries

Stage 0 freezes architecture and file contracts. Stage 1 supplies Composer, bootstrap, dependency wiring, HTTP request/response objects, routing, health endpoints, central exception handling and tests. YAML repositories, localized routing and rendering begin in stage 2 and later vertical slices.
