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

## UI tabs

- **Links** — hero shorten form + personal link library
- **Configuration** — company defaults (admin edit): default expiry, min code length, HTTPS requirement, analytics toggle, password toggle, **public base URL** (prefix ending with `?c=`; blank uses application `BASE_URL`)

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
