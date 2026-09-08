# Cloudflare analytics dashboard

The admin homepage displays Cloudflare Web Analytics traffic and Core Web
Vitals. The integration is read-only and credentials remain in the server
environment.

## Cloudflare setup

1. Enable Cloudflare Web Analytics and ensure its RUM beacon is present.
2. Create an API token with account-level and zone-level **Analytics: Read**
   permissions, restricted to the intended account and zone.
3. Obtain the Account ID and the Web Analytics **site tag**. The site tag is
   the identifier used by the GraphQL siteTag filter; it may differ from the
   public beacon token.
4. Add these values to .env.local:

~~~dotenv
CLOUDFLARE_ANALYTICS_ENABLED=1
CLOUDFLARE_API_TOKEN=replace_with_read_only_token
CLOUDFLARE_ACCOUNT_ID=replace_with_account_id
CLOUDFLARE_ZONE_ID=replace_with_zone_id
CLOUDFLARE_WEB_ANALYTICS_SITE_TAG=replace_with_site_tag
CLOUDFLARE_ANALYTICS_HOSTNAME=www.example.com
~~~

The hostname is optional. It is useful when a Web Analytics property contains
more than one hostname. Cache lifetime, request timeout and display timezone
can also be configured; see .env.example.

## Dashboard behavior

The admin homepage supports 24-hour, 7-day, 30-day and 90-day ranges. It shows:

- page views, visits, views per visit and comparison with the preceding period;
- traffic trend, top paths and visitor countries;
- device, browser and operating-system breakdowns;
- P75 Largest Contentful Paint, Interaction to Next Paint and Cumulative
  Layout Shift with Google's good/needs-improvement/poor thresholds;
- CSV export for the selected range.

Responses are cached under storage/cache/analytics. If Cloudflare is
temporarily unavailable, the last cached result is shown. Tokens and complete
API responses are never rendered in the admin panel or written to application
logs.

If analytics is disabled or incomplete, the admin homepage stays operational
and presents a configuration message. If the traffic query succeeds but Web
Vitals are unavailable for the account, traffic remains visible and only the
Vitals section reports that limitation.
