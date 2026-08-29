# Public API — version 1

The public API is read-only and returns JSON encoded as UTF-8. Clients never
read YAML directly. The `lang` query parameter is optional and defaults to the
enabled default language from `config/languages.yml`.

## Endpoints

| Method | Endpoint | Result |
|---|---|---|
| GET, HEAD | `/api/v1/health` | runtime status |
| GET, HEAD | `/api/v1/pages?lang=pl` | homepage |
| GET, HEAD | `/api/v1/pages/oferta?lang=pl` | page matched by localized public path |
| GET, HEAD | `/api/v1/navigation?lang=pl` | all localized menus |
| GET, HEAD | `/api/v1/config?lang=pl` | public setup projection and language metadata |

Nested page paths are supported, for example
`/api/v1/pages/oferta/strony-www?lang=pl`. API paths do not include the locale
prefix because locale selection is explicit in `lang`. The returned website
URL does include `/pl` or `/en` when multiple languages are enabled.

`GET /api/v1/pages` is deliberately the homepage endpoint. A future optional
page-list endpoint will use a distinct route so it cannot silently change this
contract.

## Page response

```json
{
  "id": "services",
  "locale": "pl",
  "url": "/pl/oferta",
  "layout": "default",
  "title": "Oferta",
  "seo": {
    "title": "Oferta — Example",
    "description": "Opis",
    "canonical": "https://example.com/pl/oferta",
    "robots": { "index": true, "follow": true },
    "openGraph": {},
    "twitter": {},
    "jsonLd": {}
  },
  "blocks": []
}
```

Disabled blocks are validated but omitted from the public representation. Block
data is validated, normalized and localized according to the developer-owned
`block.yml` definition. Unknown block types, unknown fields, invalid UUIDs and
missing translations make the page invalid instead of leaking unchecked data.

## Conditional requests

Successful content responses include:

- `ETag`, calculated from the exact JSON representation;
- `Last-Modified`, based on all source files affecting the response;
- `Cache-Control: public, max-age=0, must-revalidate`.

Clients may send `If-None-Match` or `If-Modified-Since`. An unchanged resource
returns HTTP 304 with an empty body.

## Errors

```json
{
  "error": {
    "code": "PAGE_NOT_FOUND",
    "message": "Page not found"
  }
}
```

Relevant stable codes are `PAGE_NOT_FOUND`, `LANGUAGE_NOT_AVAILABLE`,
`ROUTE_NOT_FOUND` and `METHOD_NOT_ALLOWED`. Invalid on-disk configuration is a
server fault and is never returned with filesystem details in production.
