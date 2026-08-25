# AGENT_NOTES.md - Short URLs

## 1. Module Purpose

Employee-scoped URL shortener: paste a long URL, optional custom code, password, expiration, and linked QR code. Click analytics on public redirects. Company defaults on the Configuration tab.

Bidirectional integration with `modules/qr/`: short links can spawn dynamic website QR codes; website dynamic QR codes can shorten the destination first.

## 2. Key Tables

- **short_urls** — per-employee links (`destination_url`, `short_code`, `access_token`, `password_hash`, `expires_at`, `click_count`, `qr_code_id`)
- **short_url_clicks** — click analytics (no audit triggers)
- **short_url_settings** — per-company defaults (expiry days, code min length, HTTPS requirement, analytics, password allow, **public_base_url**)

## 3. Required Relationships

- **short_urls** → `companies`, `employees` (owner scope)
- **short_urls.qr_code_id** → `qr_codes` (SET NULL on delete)
- **qr_codes.short_url_id** — back-link index (no FK; app-maintained)
- **short_url_clicks** → `short_urls`, `companies`

## 4. Business Rules (Critical for Agents)

- Authenticated queries: `company_id` + `employee_id` = session owner.
- Public `go.php` (root alias) resolves by `?c=` (short_code) or `?t=` (access_token); legacy `modules/short-url/go.php` uses the same handler; rate-limited; password gate via session key.
- Expired links return HTTP 410.
- Configuration tab: admin edit only (`itm_is_admin($conn, $employeeId)`); all users read. `public_base_url` optional — valid `http`/`https` prefix before short code; empty uses `itm_short_url_default_public_base_prefix()` (`BASE_URL` + `/go.php?c=`).
- No Add sample data.

## 5. UI Behavior Requirements

Bespoke UI with `?tab=links|configuration` (emails-style tabs). Hero shorten form on Links tab with feature cards (custom code, password, expiry, QR). Expiration uses native `type="date"` calendar picker (`itm_date_input_iso_value()` on edit). Gate-excluded in `scripts/data/ui_configuration_excluded_modules.txt`.

- **index.php** — router; tabs in `includes/partials/render.php`; Links library table shows **Clicks** (short URL redirects) and **Scans** (linked `qr_codes.scan_count` via `qr_code_id` or `short_url_id` back-link).
- **go.php** — public redirect (`ITM_SHORT_URL_PUBLIC`)
- Table opts out of table-tools import/export.

## 6. API / AJAX Actions

- `POST short_url_action=save` — create/update link (CSRF)
- `POST save_short_url_settings` — configuration tab (admin, CSRF)

## 7. File Structure

| Path | Role |
|------|------|
| `index.php` | Router |
| `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` | Wrappers |
| `go.php` | Public redirect |
| `includes/handlers.php` | POST handlers |
| `includes/bootstrap.php` | List/view data |
| `includes/partials/render.php` | HTML shell |
| `includes/partials/tab_links.php` | Hero + library |
| `includes/partials/tab_configuration.php` | Company settings |

Shared logic: `includes/itm_short_url.php`. Client: `js/itm-short-url.js`.

## 8. Multi-Tenant Rules

- `company_id` hidden in UI; `short_code` unique per company.
- Public endpoints validate code/token only; generic 404 on invalid.

## 9. Audit Logging

- `trg_short_urls_audit_*`, `trg_short_url_settings_audit_*` in `db/03_triggers.sql`
- No triggers on `short_url_clicks`

## 10. Known Pitfalls

- Migration `db/migrations/short_url.sql` is destructive to `qr_codes` / `qr_code_scans` rows on live DBs — back up first.
- Circular FK avoided: only `short_urls.qr_code_id` has FK to `qr_codes`; `qr_codes.short_url_id` is indexed only.

## 11. Related Documentation

- `docs/SHORT_URL.md`
- `docs/QR.md` (bidirectional integration)
- Regression: `php scripts/verify_short_url.php`
- Browser: [verify_short_url.php?run=1](http://localhost/it-management/scripts/verify_short_url.php?run=1) (Admin session)
- Module: [modules/short-url/index.php](http://localhost/it-management/modules/short-url/index.php) (Admin session)

## 12. Regression Commands

```bash
php scripts/verify_short_url.php
php scripts/verify_qr.php
```
