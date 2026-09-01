# Content contracts — version 1 draft

These contracts establish the stable on-disk vocabulary. Detailed field validation is implemented in later stages.

## `config/languages.yml`

```yaml
default: pl
languages:
  pl:
    name: Polski
    enabled: true
  en:
    name: English
    enabled: true
```

One enabled language produces unprefixed routes. More than one enabled language requires a locale prefix.
The default language is the required source of truth for localized content.
Translations for other enabled languages are optional: when they are absent,
the repository resolves the default-language title, slug, SEO value or block
field. This allows another language to be enabled without migrating every
existing content file first.

## `pages/<identity>/content.yml`

```yaml
schemaVersion: 1
enabled: true
layout: default
slug:
  pl: oferta
  en: services
title:
  pl: Oferta
  en: Services
seo:
  title:
    pl: Oferta
    en: Services
  description:
    pl: Opis strony.
    en: Page description.
  canonical: null
  robots:
    index: true
    follow: true
  openGraph: {}
  twitter: {}
  jsonLd: {}
blocks:
  - id: 01994d31-4fd1-7f32-9c2a-e89d624cda37
    type: hero
    enabled: true
    data: {}
```

`slug` is omitted for `pages/homepage/content.yml`. The directory path is the stable internal page identity. Public slugs are unique among siblings for each locale.

## `blocks/<type>/block.yml`

```yaml
schemaVersion: 1
name:
  pl: Hero
  en: Hero
description:
  pl: Główna sekcja strony
  en: Main page section
icon: image
fields:
  title:
    type: text
    required: true
    translatable: true
    minLength: 1
    maxLength: 160
```

`block.yml` and `render.php` are required. `style.css`, `script.js` and `preview.webp` are optional developer assets.
Block data may contain only fields declared by the definition. Required
translatable fields require a value in the default site language. Other
enabled languages fall back to that value until an explicit translation is
saved. Field
definitions and supported validation rules are documented in
[BLOCKS.md](BLOCKS.md).

## `pages/<identity>/pagination.yml`

```yaml
schemaVersion: 1
type: collection
enabled: true
layout: collection
slug:
  pl: aktualnosci
  en: blog
title:
  pl: Aktualności
  en: Blog
source: children
sort:
  field: date
  direction: desc
pagination:
  perPage: 12
filters:
  - parameter: category
    field: category
    allowedValues: [news, guide]
```

A directory containing `pagination.yml` and no `content.yml` is a collection. A directory cannot be both a normal page and a collection in contract version 1.
The collection is also a valid ancestor for child pages. Sort and filter fields
are read from additional top-level properties of each child `content.yml`, for
example `date`, `category` or a localized `excerpt`.

## `config/setup.yml`

```yaml
schemaVersion: 1
site:
  name: Example
  url: https://example.com
  defaultLayout: default
seo:
  titleSuffix: Example
  description: ''
  ogImage: null
media:
  maxUploadBytes: 26214400
  allowedMimeTypes:
    - application/pdf
    - image/jpeg
    - image/png
    - image/svg+xml
    - image/webp
  stripMetadata: true
  transformations:
    enabled: true
    quality: 82
    maxWidth: 4096
    maxHeight: 4096
    maxPixels: 40000000
  cache:
    enabled: true
    maxVariantsPerMedia: 64
  formats:
    - webp
    - avif
```

`site.url` is the canonical public URL and is owned exclusively by this file.
Media behavior is also site configuration. Runtime cache of parsed YAML is a
separate infrastructure concern controlled exclusively through
`YAML_CACHE_JSON_ENABLED` and `YAML_CACHE_SERIALIZE_ENABLED` in `.env.local`.

## Internal links

Internal page references use stable identities rather than localized URLs:

```yaml
link:
  type: page
  page: services/websites
```

External links use:

```yaml
link:
  type: url
  url: https://example.com
```

The URL generator resolves page references for the current locale. This rule also applies to navigation.

Collections use their stable technical identity in the same way:

```yaml
link:
  type: collection
  collection: blog
```

## Public configuration projection

`GET /api/v1/config` will expose a deliberate public projection, never the raw `setup.yml`. Secrets are forbidden in content/configuration YAML regardless of API projection.
