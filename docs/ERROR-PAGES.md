# Error pages

Public website errors are rendered from block-based definitions stored in
`errors/<status>/content.yml`. They use the same layouts, block definitions,
assets and localization rules as regular pages, but they are not public routes
and are not included in navigation, sitemaps or the public content API.

Resolution is deterministic:

1. `errors/<exact-status>/content.yml`, for example `errors/404/content.yml`;
2. `errors/<status-family>/content.yml`, for example `errors/400/content.yml`;
3. the built-in minimal HTML response when neither definition can be rendered.

The selected definition never changes the HTTP response status. A 404 rendered
with the `errors/400` fallback still returns HTTP 404.

Block and layout render templates receive an optional `$error` object with:

```php
$error?->status();      // 404
$error?->description(); // Not Found
$error?->homepageUrl(); // /en/ on a multilingual site, / otherwise
```

Visible copy should normally be configured as translatable block data. The
description is a safe standard HTTP phrase and never contains exception details.
The locale is taken from the first URL segment when multilingual routing is in
use; otherwise the configured default locale is used.

In the bundled `error-message` block, a configured `buttonUrl` equal to `/` is
treated as the homepage. On multilingual sites it is automatically changed to
the active locale's homepage (for example `/en/`), while single-language sites
keep `/`.

Requests to `/api/`, and requests which explicitly negotiate JSON, never use the
HTML pages. They keep the standard envelope:

```json
{"error":{"code":"PAGE_NOT_FOUND","message":"Page not found"}}
```

The administration login redirect and administration no-cache behavior remain
unchanged. Error-page rendering is guarded: malformed error content or another
rendering failure falls back to the built-in HTML response instead of causing a
recursive failure.
