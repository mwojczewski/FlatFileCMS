# Page administration

Stage 8 exposes the first content-management area at `/admin/pages`. Every
operation requires an authenticated administrator session and every mutation
requires a valid CSRF token.

Collection directories are shown as read-only tree nodes so their child pages
remain in context. A page may be created below a collection; collection
configuration editing is delivered separately.

## Editable fields

The metadata editor manages:

- public enabled state;
- a layout selected only from `templates/layouts/*.php`;
- translated page titles and public slugs;
- translated SEO titles and descriptions;
- canonical URL and robots index/follow values.

Existing blocks, custom collection fields and developer-defined page
attributes are retained as data when metadata is changed. Block editing
is introduced in stage 9.

## Technical identity and public slug

The technical identity is the relative page directory, for example
`services/websites`. It is always composed of lowercase ASCII slugs and is not
the public URL. Public slugs remain translated values in `content.yml`.

Creating `services/websites` requires the `pages/services/` directory to exist.
Moving a page renames its complete directory, including children and page-local
media. The homepage identity `homepage` can be edited but cannot be created,
moved or deleted through the panel.

## Concurrency and validation

Every edit form contains the SHA-256 revision of the loaded `content.yml`. A
stale form receives HTTP 409 and cannot overwrite a newer save. Tree mutations
share an exclusive pages-tree lock.

Before a write is accepted the CMS validates:

- page and localized slug contracts;
- the selected developer layout;
- every existing block against its current definition;
- collisions across page and collection routes for every enabled locale;
- target-directory and parent-directory constraints.

A page is public immediately after a successful write unless `enabled` is
unchecked.

## Deletion

Deletion requires typing `delete`. It permanently removes the page directory,
all descendants and all page-local media. There is deliberately no trash,
database copy or revision history. Deployment backups remain an operational
responsibility.
