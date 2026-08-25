# QR Generator (`modules/qr`)

Employee-scoped QR code library with static and dynamic encoding, design options, downloads (PNG/JPG/SVG), and scan analytics.

## Tables

- `qr_codes` — owner `employee_id` + `company_id`; `type_slug`, `encoding_mode` (`static`|`dynamic`), `payload_json`, `encoded_payload`, `access_token`, `design_json`, `scan_count`, `short_url_id` (optional link to Short URLs module)
- `qr_code_scans` — per-scan analytics (no audit triggers)

## Public endpoints (no login)

- `modules/qr/r.php?t={token}` — dynamic landing or redirect (website)
- `modules/qr/asset.php?t={token}&p={path}` — token-scoped file serve for landing assets

## Separate from SpeedyShare

Temporary cross-device share uses `share_sessions` and `includes/itm_qr_share.php` — not this module.

## Short URLs integration

Website + dynamic QR codes may shorten the destination via **Shorten URL with Short URLs** (`modules/qr/` create/edit). Short URLs can create a linked dynamic website QR. See [docs/SHORT_URL.md](SHORT_URL.md).

## Regression

```bash
php scripts/verify_qr.php
```

Browser: [verify_qr.php?run=1](http://localhost/it-management/scripts/verify_qr.php?run=1)

Module: [modules/qr/index.php](http://localhost/it-management/modules/qr/index.php) (Admin session)
