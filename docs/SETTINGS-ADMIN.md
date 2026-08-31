# Navigation and global settings

Stage 12 exposes two authenticated, filesystem-backed editors. Neither editor
stores data in SQLite and neither permits editing PHP or arbitrary files.

## Navigation editor

`/admin/navigation` manages every menu in `config/navigation.yml`. It supports:

- multiple named menus;
- localized labels for every enabled language;
- stable references to pages and collections;
- safe internal, HTTP(S), `mailto:` and `tel:` URLs;
- `_self` and `_blank` targets;
- nested children, reordering, promotion and deletion.

Dragging moves an item before another item at the same nesting level. Explicit
“Dodaj dziecko” and “Wysuń” controls make hierarchy changes usable on touch
devices where drag and drop is less reliable.

Before an atomic write, the complete document is resolved for every enabled
locale against the current page and collection route index. Missing resources,
invalid translated labels, unknown properties, duplicate menu names, more than
250 items or nesting deeper than eight levels reject the entire write. The
previous YAML remains untouched.

Every form carries the SHA-256 revision of the loaded file. A concurrent edit
returns HTTP 409 instead of silently overwriting newer navigation.

## Global configuration editor

`/admin/settings` presents sticky component navigation and separate cards for
site identity, SEO, media, and public text files. It edits an explicit allowlist
in `config/setup.yml`:

- site name, canonical URL and registered default layout;
- localized default SEO title suffixes and descriptions;
- OpenGraph, Twitter/X and JSON-LD JSON structures;
- upload size, MIME allowlist and metadata removal;
- image limits, output formats, transformations and variant cache.

The selected layout must exist in `templates/layouts/`. Numeric and media
limits pass through the same `MediaConfig` validation used at runtime. JSON
fields are bounded and must decode to the expected object/array shapes.

Unknown developer-defined top-level and nested keys are preserved. The editor
cannot see or modify `.env.local`, SMTP credentials, `APP_SECRET`, session
settings or WebAuthn relying-party configuration.

The public-text cards atomically create `config/llms.txt` and
`config/security.txt`. Empty or missing files return 404; saved values are
served unchanged from `/llms.txt` and `/.well-known/security.txt`.

## Redirect editor

`/admin/redirects` manages `config/redirects.yml`. Each exact source path can
target an internal path or an absolute HTTP(S) URL and use status 301, 302, 303,
307 or 308. Duplicate sources, self-redirects, unsafe header characters and
redirect cycles reject the whole revision-checked write.

Successful writes add `navigation.updated` or `config.updated` to the JSONL
audit trail. Both endpoints require an authenticated account and a valid CSRF
token.

## Collection settings

`/admin/collections/edit?path=…` edits the existing collection's
`pagination.yml`. The form covers localized title and slug values, layout,
enabled state, SEO, sort field and direction, page size and extensible filter
definitions. The collection type and `children` source remain fixed.

The complete candidate is validated through `CollectionRepository`,
`LayoutRegistry` and `PageRouteIndex` before an atomic revision-checked write.
Invalid filters, route collisions or stale forms leave the original file
untouched.

## Admin view boundary

Page-level panel markup is stored under `templates/admin/` and rendered by the
root-bound `AdminView`. Controllers prepare data and coordinate domain
services; they no longer concatenate page HTML. Schema-generated block field
widgets remain isolated in `BlockFormRenderer`, because they are an extensible
field-rendering adapter rather than route views.
