# Dynamic page builder

Stage 9 adds a schema-driven block editor at
`/admin/pages/builder?path=<technical-page-identity>`. It edits only the
`blocks` list in an existing page `content.yml`; page metadata remains owned by
the stage 8 editor.

## Developer and administrator boundary

The picker is generated from the packages discovered by `BlockRegistry`.
Administrators can manage block instances and their data, but cannot edit
`block.yml`, `render.php`, styles, scripts or previews. Adding a developer block
package automatically adds it to the picker and form generator.

The optional `preview.webp` is served only through an authenticated, type-based
preview endpoint. Arbitrary filesystem paths are never accepted.

## Supported operations

- add a block selected from the registry;
- edit data through controls generated from its field definitions;
- duplicate a block with a fresh UUID v7;
- enable or disable a block without deleting its content;
- reorder the complete block list with drag and drop;
- permanently delete a block instance.

Forms support every built-in field type, localized fields, localized image ALT
values and recursively generated repeaters. Language controls are generated
from `config/languages.yml`, not hard-coded in the panel.

Every `image` and `file` control can open the authenticated media picker for
the page being edited. Selection writes only the page-local filename into the
form. Upload, previews and deletion live in the linked page media library; a
referenced file cannot be deleted until its block reference is removed.

Fields declared as `type: markdown` are progressively enhanced with the locally
vendored EasyMDE 2.21.0 editor. The integration also initializes Markdown
fields added later by repeater controls and refreshes hidden editors after a
locale tab becomes visible. No editor CSS, JavaScript, fonts or icons are loaded
from a CDN. Submitted values remain Markdown; EasyMDE never becomes a content
storage format.

Preview HTML is sanitized in the browser before insertion into the admin DOM.
The public and server-side output still passes through the independent backend
`MarkdownRenderer`, so client-side previewing does not weaken the content
rendering boundary.

## Persistence and concurrency

Every builder form carries the SHA-256 revision of the loaded `content.yml`.
A mutation obtains an exclusive page-specific lock, reads the current document
again and rejects a stale revision with HTTP 409.

Before atomic persistence, the resulting page and every block — including
disabled blocks — are validated against their current definitions for every
enabled language. A failed schema or media validation does not change the YAML
file. Unknown fields, block types, identifiers or incomplete reorder requests
are rejected.

The page and collection hierarchy remains independent of the builder. Pages
and collections may be mixed in the filesystem; only directories containing
`content.yml` expose block editing.
