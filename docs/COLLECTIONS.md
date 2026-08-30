# Collections

A collection is a directory containing `pagination.yml` and no `content.yml`.
Its public path comes from translated slugs in exactly the same way as a normal
page. It can also act as a route ancestor for pages stored below it.

```text
pages/blog/
├── pagination.yml
├── first-post/content.yml
└── second-post/content.yml
```

## Configuration

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
seo:
  description:
    pl: Najnowsze artykuły.
    en: Latest articles.
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

Version 1 supports the `children` source, which selects enabled direct child
pages only. Descendants nested more deeply are not included implicitly.
`perPage` accepts values from 1 through 100.

## Child fields

Additional top-level values in a child `content.yml` become collection
attributes:

```yaml
date: '2026-08-30'
category: news
excerpt:
  pl: Opis wpisu.
  en: Post excerpt.
```

Attributes are localized before filtering and serialization. Sorting supports
attribute names plus the built-in fields `id`, `title` and `modifiedAt`.
Missing sort values are placed after values that exist; identity provides a
stable tie-breaker.

## Filtering and pagination

Every public filter must be declared in `pagination.yml`. Version 1 implements
strict equality; if the child value is a list, equality succeeds when it
contains the requested value. `allowedValues` is optional but recommended for
bounded public query parameters.

```text
GET /api/v1/collections/aktualnosci?lang=pl&category=news&page=2
GET /pl/aktualnosci?category=news&page=2
```

The response reports the requested page even when it is beyond the final page,
in which case `items` is empty. This keeps pagination deterministic and avoids
redirect behavior in the headless API.

## Rendering

The default developer layout is `templates/layouts/collection.php`. It receives
already localized `$collection`, `$items`, `$pagination`, `$filters`, `$seo`,
`$navigation`, `$assets` and the restricted `$context`. It does not read YAML or
the filesystem itself.
