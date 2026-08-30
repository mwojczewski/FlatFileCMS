# Configuration ownership

Every setting has one authoritative source. Environment variables do not
override site YAML, and site YAML does not contain server credentials or
secrets. This prevents configuration drift and makes a copied site tree behave
predictably after its environment has been supplied.

## `.env.local`

`.env.local` is untracked and belongs to the deployment/runtime operator.

| Concern | Variables |
|---|---|
| Runtime | `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE` |
| Infrastructure cache | `YAML_CACHE_JSON_ENABLED`, `YAML_CACHE_SERIALIZE_ENABLED` |
| Reverse proxy | `TRUSTED_PROXIES` |
| Session deployment | `SESSION_NAME`, `SESSION_LIFETIME`, `SESSION_COOKIE_SECURE`, `SESSION_COOKIE_SAME_SITE` |
| Auth protection | `AUTH_LOGIN_*`, `AUTH_RESET_*`, `AUTH_PASSWORD_RESET_TTL` |
| WebAuthn deployment | `WEBAUTHN_RP_ID`, `WEBAUTHN_RP_NAME` |
| Mail transport | `MAIL_*` |

`HttpOnly` is not configurable: administrator session cookies must always use
it. Other non-negotiable security guarantees should likewise be enforced in
code instead of exposed as switches.

`WEBAUTHN_RP_ID` is the exact admin hostname without scheme, port or path.
WebAuthn credentials are cryptographically bound to it, so changing this value
makes previously registered keys unusable. `WEBAUTHN_RP_NAME` is only the label
shown by the browser during registration.

`YAML_CACHE_JSON_ENABLED` controls the JSON representation of parsed
mappings under `storage/cache/yaml/`. `YAML_CACHE_SERIALIZE_ENABLED` controls an
independent representation produced by PHP `serialize()`. Either cache may run
alone. When both are enabled, every parsed or written YAML document updates
both formats; serialized data is read first and JSON remains a revision-checked
fallback. Both switches belong to the environment because the useful choice
depends on the server and deployment mode and because reading `setup.yml`
itself uses these caches.

`MAIL_TRANSPORT` currently accepts only `smtp`. `MAIL_ENCRYPTION` accepts
`none`, `starttls` or `smtps`; production deployments should use the mode
required by their provider and keep SMTP credentials exclusively in the
environment. `MAIL_FROM_ADDRESS` is required when password recovery is used.

## `config/setup.yml`

`setup.yml` is versioned, copied with the site and may later be managed through
the admin application.

| Concern | Keys |
|---|---|
| Site identity | `site.name`, `site.url` |
| Rendering | `site.defaultLayout` |
| SEO defaults | `seo.*` |
| Media transforms | `media.transformations.enabled`, `media.formats` |
| Generated media cache | `media.cache.enabled` |

`site.url` is the sole canonical site URL. There is no `APP_URL` environment
override. Media processing has no environment override either; copying the
site preserves its declared media contract.

## Other YAML files

- `config/languages.yml` owns enabled languages and the default locale;
- `config/navigation.yml` owns menus;
- `pages/**/content.yml` owns page content, localized slugs, page SEO and
  blocks;
- `blocks/*/block.yml` is developer-owned schema, not administrator content.

Secrets are forbidden in every YAML file. SMTP passwords, application secrets
and future external-service credentials belong only to the environment or an
equivalent secret manager exposed as environment variables.
