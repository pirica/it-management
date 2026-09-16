# Short URLs (`modules/short-url`)

Employee-scoped link shortener with custom codes, optional password and expiration, click analytics, and bidirectional QR Generator integration.

## Tables

- `short_urls` — owner `employee_id` + `company_id`; `destination_url`, `short_code`, `access_token`, `password_hash`, `expires_at`, `click_count`, `qr_code_id`
- `short_url_clicks` — per-click analytics (no audit triggers)
- `short_url_settings` — per-company defaults (one row per tenant); optional `public_base_url` prefix before the short code (falls back to `BASE_URL` + `/go.php?c=`)

## Public endpoint (no login)

- `go.php?c={short_code}` — canonical short redirect (app root alias)
- `go.php?t={access_token}` — token fallback lookup
- `modules/short-url/go.php?c=` / `?t=` — legacy path (same handler)

Unknown, expired, or unavailable links render a simple HTML message (for example **Invalid short URL. This link is invalid or no longer available.**) with HTTP 404/410 — not a blank page.

## UI tabs

- **Links** — hero shorten form + personal link library
- **Configuration** — company defaults (admin edit): default expiry, min code length, **HTTPS requirement** (default on), **domain allowlist** (optional enforcement + textarea), **interstitial warning** (default on), **creation rate limit per employee/hour** (default 30; `0` = unlimited), analytics toggle, password toggle, **public base URL** (prefix ending with `?c=`; blank uses application `BASE_URL`)

## Public redirect security

- **Save-time policy** — `itm_short_url_validate_save()` calls `itm_short_url_destination_passes_policy()` (HTTPS + optional allowlist).
- **Redirect-time policy** — `go.php` uses `itm_short_url_resolve_public_redirect()` before any 302 (blocks stale HTTP rows when HTTPS is required).
- **Interstitial** — when `interstitial_warning_enabled`, visitors confirm via POST before redirect (`itm_short_url_render_interstitial_page()`).
- **Creation throttle** — `itm_short_url_creation_rate_limit_check()` on new links (file-backed per `company_id` + `employee_id`).

Configuration: [modules/short-url/index.php?tab=configuration](http://localhost/it-management/modules/short-url/index.php?tab=configuration) (Admin session)

## QR integration

| Direction | Behaviour |
|-----------|-----------|
| Short URL → QR | Checkbox **Generate QR Code** on create; creates dynamic `website` QR encoding the **short public URL** |
| QR → Short URL | Website + dynamic: checkbox **Shorten URL with Short URLs** on create/edit |

Back-links: `short_urls.qr_code_id` and `qr_codes.short_url_id`.

## Regression

```bash
php scripts/verify_short_url.php
php scripts/verify_qr.php
```

Browser: [verify_short_url.php?run=1](http://localhost/it-management/scripts/verify_short_url.php?run=1)

Module: [modules/short-url/index.php](http://localhost/it-management/modules/short-url/index.php) (Admin session)

Configuration: [modules/short-url/index.php?tab=configuration](http://localhost/it-management/modules/short-url/index.php?tab=configuration)

See also: [docs/QR.md](QR.md)
