# Server-side rendering

Stage 5 adds HTML as a second adapter over the same content model used by the
REST API. `PageViewModelFactory` performs the shared page assembly: localized
metadata, resolved SEO and validated, normalized blocks. `PageSerializer`
projects that model to JSON while `PageRenderer` projects it to HTML. Neither
adapter performs an HTTP request to the other.

## Rendering pipeline

```text
PageRepository + configuration + navigation
                    |
                    v
           PageViewModelFactory
              /             \
             v               v
      PageSerializer     PageRenderer
             |               |
            JSON     blocks -> layout -> HTML
```

`BlockProcessor` validates every block and resolves its translatable fields
before `BlockRenderer` receives the data. Disabled blocks are validated but do
not reach either public adapter.

## Developer templates

Layouts are regular developer-owned PHP files in `templates/layouts/`. A page
selects a layout by its safe registry name, for example `layout: default`; a
filesystem path can never come from content. Partials follow the same rule in
`templates/partials/` and are rendered with:

```php
<?= $context->partial('navigation', ['menus' => $navigation]) ?>
```

The template context deliberately exposes only:

- `escape()` for HTML text and attributes;
- `markdown()` using CommonMark with raw HTML stripped and unsafe links disabled;
- `image()` for normalized page-local image data;
- `asset()` for root-relative published assets;
- `url()` for validated URLs;
- `locale()`;
- `partial()` through the fixed partial registry.

It does not expose the dependency container, PDO, sessions, request data,
filesystem services, SMTP configuration or application secrets. PHP templates
remain trusted developer code and are not editable from the CMS panel.

## Block assets

For each enabled block type present on a page, `AssetCollector` checks only the
fixed optional files `style.css` and `script.js`. Every type is processed once.
Assets are bounded to 1 MiB, symlinks are rejected and source contents receive a
SHA-256 fingerprint. Published URLs look like:

```text
/assets/blocks/hero/hero.91f3a817a4c8219e.css
```

Published copies live below `public/assets/blocks/` and are runtime-generated,
so that directory is excluded from Git. The PHP process needs write permission
to `public/assets/`; immutable deployments should publish assets during their
build/deploy step by warming the rendered pages.

## Website routing

For one enabled language, public URLs have no language prefix. For multiple
languages, every HTML URL uses a locale prefix. An unprefixed request is
redirected to the same path under the configured default locale:

```text
/oferta -> /pl/oferta
/        -> /pl/
```

HTML responses use the same page visibility and translated-slug route index as
the API. They also include ETag, Last-Modified and revalidation cache headers.

Page-local media references remain relative to the rendered page URL. Upload,
sanitized SVG delivery, image transformations and dedicated media routing are
implemented in the later media stage.
