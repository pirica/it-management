# AGENT_NOTES.md - QR Generator

## 1. Module Purpose

Employee-scoped QR code library: create static or dynamic QR codes for URLs, WiFi, contacts, media, menus, coupons, and more. Includes design options (colors, size, correction, logo), PNG/JPG/SVG download via client-side `qrcode.min.js`, and scan analytics for dynamic codes.

Separate from temporary SpeedyShare (`share_sessions` / `includes/itm_qr_share.php`).

**Short URLs integration:** website + dynamic QR may create a linked `short_urls` row (`qr_codes.short_url_id`); Short URLs module can create linked QR via `short_urls.qr_code_id`. See `docs/SHORT_URL.md` and `includes/itm_short_url.php`.

## 2. Key Tables

- **qr_codes** — per-employee saved codes (`type_slug`, `encoding_mode`, `payload_json`, `encoded_payload`, `access_token`, `design_json`, `scan_count`, `short_url_id`)
- **qr_design_templates** — per-employee design presets (`name`, `design_json`)
- **qr_code_scans** — scan analytics (no audit triggers; exempt in `check_audit_logs_coverage.php`)

## 3. Required Relationships

- **qr_codes** → `companies` (`company_id`, CASCADE)
- **qr_codes** → `employees` (`employee_id`, CASCADE) — owner scope for all authenticated queries
- **qr_code_scans** → `qr_codes` (`qr_code_id`, CASCADE)

## 4. Business Rules (Critical for Agents)

- All authenticated queries: `company_id` + `employee_id` = session owner.
- File/media types (`pdf`, `images`, `video`, `mp3`, `menu`, etc.) are **dynamic only**.
- Website / Facebook / Instagram require a valid URL on save (`itm_qr_generator_validate_url_field()`); wizard Content step blocks Next/Save until required fields pass HTML5 validation (`js/itm-qr-generator.js` → `validateContentStep()`).
- `wifi`, `sms`, `whatsapp`, `text` are **static only**.
- Static QR embeds `encoded_payload` directly; dynamic QR encodes `modules/qr/r.php?t={access_token}`.
- Uploads: `files/{company_id}/Common/qr/{employee_id}/` via `itm_qr_generator_ensure_upload_dir()`.
- Public assets on landings: `modules/qr/asset.php?t=&p=` (token-scoped path allowlist from payload).
- No Add sample data.

## 5. UI Behavior Requirements

Bespoke wizard (not flattened CRUD `$uiColumns`). Gate-excluded in `scripts/data/ui_configuration_excluded_modules.txt` (synced with `docs/list_bespoke_UI.txt`). Intentional contract gaps documented in `scripts/data/ui_configuration_reviewed.json` (column sort, bulk delete).

- **index.php** — thin router; list markup in `includes/partials/render.php` with `data-itm-new-button-managed` header row.
- **create.php** — step 1 type grid; step 2 wizard with **Content** (1) and **Design** (2) tabs, live static preview, PNG/JPG/SVG download, **design templates** (`qr_design_templates` per employee). Coupon **Expires** uses **`itm_render_uk_date_input()`** (dd/mmm/yyyy + 📅 picker); landing shows dd/mmm/yyyy via `itm_format_date_display()`.
- **edit.php** — same wizard for existing row.
- **view.php** — preview, download buttons, scan table (last 50).
- **r.php** / **asset.php** — public, no login (`ITM_QR_GENERATOR_PUBLIC`).

Table opts out of table-tools import/export.

## 6. API / AJAX Actions

- `POST qr_action=save` — create/update (CSRF)
- `POST qr_action=upload_asset` — JSON file upload for wizard

## 7. File Structure

| Path | Role |
|------|------|
| `index.php` | Router + list |
| `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` | Wrappers |
| `r.php` | Public dynamic resolver |
| `asset.php` | Public token-scoped file serve |
| `includes/handlers.php` | POST handlers |
| `includes/bootstrap.php` | List/wizard/view data |
| `includes/partials/render.php` | HTML shell |
| `includes/partials/form_fields.php` | Type-specific form fields |
| `includes/partials/landing.php` | Public landing HTML |

Shared logic: `includes/itm_qr_generator.php`. Client: `js/itm-qr-generator.js`, `js/qrcode.min.js`.

## 8. Multi-Tenant Rules

- `company_id` hidden in UI; never expose other employees' codes.
- Public endpoints validate `access_token` only; generic 404 on invalid token.

## 9. Audit Logging

- `trg_qr_codes_audit_*` on `qr_codes` in `db/03_triggers.sql`
- No triggers on `qr_code_scans`

## 10. Known Pitfalls

- Dynamic preview before first save shows hint only (no public URL until saved).
- Re-import `db/01_schema.sql` on existing DBs to add tables, or run `db/migrations/qr_design_templates.sql` via `php scripts/migrate.php --apply`.

## 11. Related Documentation

- `docs/QR.md`
- `docs/list_bespoke_UI.txt` (slug `qr`)

## 12. Module Owner Notes

- Regression: `php scripts/verify_qr.php`
- Browser: [verify_qr.php?run=1](http://localhost/it-management/scripts/verify_qr.php?run=1)
- Module: [modules/qr/index.php](http://localhost/it-management/modules/qr/index.php)
