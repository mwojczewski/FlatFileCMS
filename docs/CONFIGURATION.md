# Configuration ownership

Every setting has one authoritative source. Environment variables do not
override site YAML, and site YAML does not contain server credentials or
secrets. This prevents configuration drift and makes a copied site tree behave
predictably after its environment has been supplied.

## `.env.local`

`.env.local` is untracked and belongs to the deployment/runtime operator.

| Concern              | Variables                                                                               |
| -------------------- | --------------------------------------------------------------------------------------- |
| Runtime              | `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`                                    |
| Diagnostics          | `LOG_LEVEL`, `LOG_MAX_FILES`                                                            |
| Infrastructure cache | `YAML_CACHE_JSON_ENABLED`, `YAML_CACHE_SERIALIZE_ENABLED`                               |
| Reverse proxy        | `TRUSTED_PROXIES`                                                                       |
| Session deployment   | `SESSION_NAME`, `SESSION_LIFETIME`, `SESSION_COOKIE_SECURE`, `SESSION_COOKIE_SAME_SITE` |
| Auth protection      | `AUTH_LOGIN_*`, `AUTH_RESET_*`, `AUTH_PASSWORD_RESET_TTL`                               |
| WebAuthn deployment  | `WEBAUTHN_RP_ID`, `WEBAUTHN_RP_NAME`                                                    |
| Mail transport       | `MAIL_*`                                                                                |

`HttpOnly` is not configurable: administrator session cookies must always use
it. Other non-negotiable security guarantees should likewise be enforced in
code instead of exposed as switches.

`WEBAUTHN_RP_ID` is the exact admin hostname without scheme, port or path.
WebAuthn credentials are cryptographically bound to it, so changing this value
makes previously registered keys unusable. `WEBAUTHN_RP_NAME` is only the label
shown by the browser during registration.

`TRUSTED_PROXIES` is enforced by the request boundary. It accepts exact IPs and
CIDR ranges; forwarded client addresses are ignored unless the direct peer is
trusted. Never configure a catch-all network. See [DEPLOYMENT.md](DEPLOYMENT.md).

`YAML_CACHE_JSON_ENABLED` controls the JSON representation of parsed
mappings under `storage/cache/yaml/`. `YAML_CACHE_SERIALIZE_ENABLED` controls an
independent representation produced by PHP `serialize()`. Either cache may run
alone. When both are enabled, every parsed or written YAML document updates
both formats; serialized data is read first and JSON remains a revision-checked
fallback. Both switches belong to the environment because the useful choice
depends on the server and deployment mode and because reading `setup.yml`
itself uses these caches.

`PUBLIC_HTML_CACHE_ENABLED` enables the full-page cache for public HTML
requests. It never applies to administration, API, media, redirects, errors or
non-GET requests. Cache keys include the resolved locale, public content path,
canonical query parameters, content generation and `APP_RELEASE`. Each entry
uses PHP serialization and stores the HTML, modification timestamp and its
SHA-256 hash used as the response ETag.

`APP_RELEASE` must change with every deployment that can affect rendered
output, for example to the deployed Git commit SHA or build identifier. This
immediately moves all requests to a new cache namespace, covering changes to
PHP code, templates, block definitions and frontend assets. Successful CMS
writes to pages or configuration rotate the shared content generation and
therefore invalidate every locale atomically. Set
`PUBLIC_HTML_CACHE_ENABLED=0` to bypass both reads and writes. The cache is
disabled when the switch is absent; enabling it makes `APP_RELEASE` required.

`MAIL_TRANSPORT` currently accepts only `smtp`. `MAIL_ENCRYPTION` accepts
`none`, `starttls` or `smtps`; production deployments should use the mode
required by their provider and keep SMTP credentials exclusively in the
environment. `MAIL_FROM_ADDRESS` is required when password recovery is used.

`CLOUDFLARE_BEACON_TOKEN` optionally enables the public Cloudflare Web
Analytics beacon. When non-empty, the shared public HTML document template
adds Cloudflare's module script immediately before `</body>` and safely embeds
the configured token in `data-cf-beacon`. It is a public site tag, not the
private `CLOUDFLARE_API_TOKEN` used by the administrator analytics dashboard.
Changing the beacon token also changes the public HTML cache namespace.

## `config/setup.yml`

`setup.yml` is versioned, copied with the site and may later be managed through
the admin application.

| Concern               | Keys                                                                                              |
| --------------------- | ------------------------------------------------------------------------------------------------- |
| Site identity         | `site.name`, `site.url`                                                                           |
| Rendering             | `site.defaultLayout`                                                                              |
| SEO defaults          | `seo.*`                                                                                           |
| Upload policy         | `media.maxUploadBytes`, `media.allowedMimeTypes`, `media.stripMetadata`                           |
| Media transforms      | `media.transformations.enabled`, `quality`, `maxWidth`, `maxHeight`, `maxPixels`, `media.formats` |
| Generated media cache | `media.cache.enabled`                                                                             |

`site.url` is the sole canonical site URL. There is no `APP_URL` environment
override. Media processing has no environment override either; copying the
site preserves its declared media contract.

Image transformations and their generated-file cache are independent:
`media.transformations.enabled: false` delegates processing to an edge service,
while `media.cache.enabled: false` still permits on-demand transforms without
persisting variants. Neither value controls the JSON or serialized parsed-YAML
cache. See [MEDIA.md](MEDIA.md) for limits, supported types and public URLs.

## Other YAML files

- `config/languages.yml` owns enabled languages and the default locale;
- `config/navigation.yml` owns menus;
- `config/redirects.yml` owns exact public redirect rules;
- optional `config/llms.txt` and `config/security.txt` own public text files;
- `pages/**/content.yml` owns page content, localized slugs, page SEO and
  blocks;
- `blocks/*/block.yml` is developer-owned schema, not administrator content.

Secrets are forbidden in every YAML file. SMTP passwords, application secrets
and future external-service credentials belong only to the environment or an
equivalent secret manager exposed as environment variables.

The authenticated `/admin/settings` form updates only an explicit set of site,
SEO and media keys while preserving custom developer keys. `/admin/navigation`
edits `navigation.yml` and validates all localized links before writing. See
[SETTINGS-ADMIN.md](SETTINGS-ADMIN.md).
