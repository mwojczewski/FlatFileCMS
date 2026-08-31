# Page-local media

Stage 11 keeps every administrator-managed file beside the page that owns it:

```text
pages/services/
├── content.yml
├── hero.jpg
└── brochure.pdf
```

Media are content, not database records. Moving or deleting a page therefore
moves or deletes its media with the same filesystem operation. Block fields
store only a direct filename such as `hero.jpg`; paths, absolute URLs and
nested media directories are rejected.

## Administrator workflow

The page editor and page builder link to
`/admin/media?path=<technical-page-identity>`. This authenticated view supports
browsing, upload, preview and permanent deletion. Schema-generated `image` and
`file` controls open the same page-local library in a responsive picker.
Image previews open inside a fullscreen panel dialog. EasyMDE exposes the same
image library and inserts the selected immutable media URL as Markdown.

An uploaded filename is normalized to a portable lowercase name. Collisions
receive a numeric suffix and existing files are never overwritten. Deletion is
rejected while any `src` in the page content still references the file.

The administrator can change media data but cannot upload PHP, block code,
layouts or other executable application files through this interface.

## Upload boundary

Every upload is bounded by `media.maxUploadBytes` and checked using its actual
contents through Fileinfo. The client filename and browser-supplied MIME type
are not trusted. The configured whitelist must also map to a supported, fixed
extension.

Raster dimensions are inspected before processing and the configured pixel
limit protects against decompression bombs. JPEG and PNG uploads can be
re-encoded to remove EXIF and other ancillary metadata. SVG is parsed as XML;
DOCTYPE, entities, scripts, embedded HTML, event handlers, inline styles and
external or executable URLs are rejected or removed before the atomic write.

Media paths use the same root-bound resolver as YAML content. Absolute paths,
directory traversal, hidden names, `content.yml`, nested paths and symlinks are
not valid media targets.

## Public URLs and headless output

Originals are served through immutable fingerprinted URLs:

```text
/media/services/91f3a817a4c8219e/hero.jpg
```

The fingerprint is derived from file contents. Replacing bytes produces a new
URL, while old HTML and CDN entries cannot silently point at different data.
Responses include `ETag`, `Cache-Control: public, max-age=31536000, immutable`,
`nosniff` and byte-range support. Sanitized SVG responses receive a restrictive
Content Security Policy.

The normalized page model shared by REST and server-side rendering enriches
schema-defined `image` and `file` values without changing `content.yml`:

```json
{
  "src": "hero.jpg",
  "alt": "Zespół w biurze",
  "url": "/media/services/91f3a817a4c8219e/hero.jpg",
  "mimeType": "image/jpeg",
  "size": 182340,
  "fingerprint": "91f3a817a4c8219e",
  "width": 1600,
  "height": 900
}
```

The frontend uses `url`; it does not calculate hashes or read YAML. `src`
remains the stable page-local content reference. Metadata is added only to
fields declared as `image` or `file`, including such fields nested in
repeaters.

## Image variants

Transformable raster images accept bounded query parameters:

```text
/media/services/91f3a817a4c8219e/hero.jpg?w=960
/media/services/91f3a817a4c8219e/hero.jpg?w=960&h=640&format=webp
/media/services/91f3a817a4c8219e/hero.jpg?w=960&h=540&format=webp&fit=cover
```

`fit=contain` preserves the complete image inside the requested bounds.
`fit=cover` performs a centered crop to an exact developer-selected aspect
ratio and requires both dimensions. Images are never enlarged. Output formats are
limited by `media.formats`; the current generated formats are WebP and AVIF.
Native GD performs decoding, resizing and encoding.

`media.transformations.enabled: false` disables local transforms completely,
which is appropriate when an edge service such as Cloudflare owns image
processing. `media.cache.enabled` independently controls generated variants in
`storage/cache/media/`. Disabling that cache does not disable transforms, and
these switches are unrelated to the parsed-YAML caches in `.env.local`.

Generated variants are disposable. `php bin/cms cache:clear` removes the whole
runtime cache, including media variants, and the next request recreates any
needed file.

Block renderers control presentation through `RenderContext`; administrators
can select the source file and contextual ALT, but cannot set layout-breaking
dimensions or formats:

```php
<?= $context->image($data['image'], width: 960, height: 540, format: 'webp', fit: 'cover') ?>

<?= $context->picture(
    $data['image'],
    widths: [480, 768, 1200, 1600],
    format: 'avif',
    aspectRatio: 16 / 9,
    fit: 'cover',
    sizes: '(max-width: 768px) 100vw, 1200px',
    attributes: ['class' => 'hero__image', 'fetchpriority' => 'high'],
) ?>
```

`imageUrl()` returns only a validated variant URL for custom developer markup.

## Configuration

```yaml
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
  formats:
    - webp
    - avif
```

The complete default whitelist is in `config/setup.yml`. Server limits such as
`upload_max_filesize` and `post_max_size` must be at least as large as the CMS
limit. The PHP process needs write access to `pages/`, `storage/tmp/` and,
when variant caching is enabled, `storage/cache/media/`.
