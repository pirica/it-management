# AGENT_NOTES.md - modules/share_modules/

## 1. Module Purpose
Administrator matrix to enable or disable temporary QR / 6-digit **share** per `modules_registry` row and company (`company_module_share`). Mirrors `modules/company_module_access/` layout (matrix + registry list/CRUD wrappers); no per-cell sidebar icons.

## 2. Database Tables
- **`company_module_share`** — `(company_id, module_id, enabled)`; **DB opt-in** — share is allowed only when a row exists with `enabled = 1` (no implicit default for missing rows). Seeds include **only** share-capable registry slugs (see `itm_qr_share_capable_module_slugs()`).
- **`share_sessions`** — unified ephemeral snapshots (`module_slug`, `record_id` or `scope_path` / `scope_path_hash`); private-data exempt.

## 4. Business Rules (Critical for Agents)
- **Admin only:** `itm_is_admin()` gate on `index.php` (same as Company Module Access).
- **Runtime enforcement:** `includes/itm_module_share.php` → `has_module_share_access()`; called from `itm_qr_share_create_session()`.
- **Share implementation list:** `itm_qr_share_capable_module_slugs()` in `includes/itm_qr_share.php` — matrix shows all registry modules; only capable slugs get enabled toggles (others show **No share UI**). Capable set includes original vault/explorer modules plus CRUD record modules wired via `includes/itm_crud_record_share.php` (employees, departments, equipment, catalogs, license_management, inventory_items, suppliers, alerts, tickets, patches_updates, ops_report, and budget modules).
- **AJAX:** `toggle_share`, `bulk_toggle_share` with CSRF; UI script `js/share-modules-matrix.js`.
- **Seeds:** `db/02_data.sql` inserts `company_module_share` for active companies × share-capable `modules_registry` rows only (`enabled = 1`). Live DBs: `db/migrations/company_module_share_capable_seed.sql`.

- **Bespoke UI audit:** listed in `docs/list_bespoke_UI.txt`; `fields_missing.php` bespoke gate — Search/Import Excel pass; Sort and Pagination reviewed in `scripts/data/fields_missing_reviewed.json` (matrix shows all registry rows on one page).

## 7. File Structure
- `index.php` — matrix UI, registry CRUD via `$crud_action`, AJAX handlers
- `create.php`, `edit.php`, `view.php`, `list_all.php`, `delete.php` — thin wrappers setting `$crud_action`
- `index.html` — directory listing prevention

## 12. Module Owner Notes (Optional)
Sidebar: Admin → **📱 Share Modules** (`includes/ui_config.php`). Regression: `php scripts/verify_module_share.php`.
