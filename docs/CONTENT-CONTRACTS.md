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
translatable fields require a value for every enabled site language. Field
definitions and supported validation rules are documented in
[BLOCKS.md](BLOCKS.md).

## `pages/<identity>/pagination.yml`

```yaml
schemaVersion: 1
type: collection
source: children
sort:
  field: date
  direction: desc
pagination:
  perPage: 12
filters: []
```

A directory containing `pagination.yml` and no `content.yml` is a collection. A directory cannot be both a normal page and a collection in contract version 1.

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
  transformations:
    enabled: true
  cache:
    enabled: true
  formats:
    - webp
    - avif
```

`site.url` is the canonical public URL and is owned exclusively by this file.
Media behavior is also site configuration. Runtime cache of parsed YAML is a
separate infrastructure concern controlled exclusively through
`YAML_CACHE_ENABLED` in `.env.local`.

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

## Public configuration projection

`GET /api/v1/config` will expose a deliberate public projection, never the raw `setup.yml`. Secrets are forbidden in content/configuration YAML regardless of API projection.
