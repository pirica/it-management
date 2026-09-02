# AGENT_NOTES.md - Company Module Share

## 1. Module Purpose

Flattened CRUD for **`company_module_share`** — per-company opt-in rows that gate temporary QR / 6-digit share on capable modules. Runtime enforcement lives in `includes/itm_module_share.php` (`has_module_share_access()`); the admin matrix UI is **`modules/share_modules/`**.

## 2. Key Tables

- **`company_module_share`** — `(company_id, module_id, enabled)`; unique `(company_id, module_id)`.
- **`modules_registry`** — FK target for `module_id` (module name in list/view via FK labels).

## 3. Required Relationships

- **`company_module_share.company_id`** → `companies.id` (`ON DELETE CASCADE`)
- **`company_module_share.module_id`** → `modules_registry.id` (`ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- Share is allowed only when a row exists with **`enabled = 1`** (no implicit default for missing rows).
- Seeds in `db/02_data.sql` cover share-capable registry slugs only (`itm_qr_share_capable_module_slugs()`).
- **`active`** is the soft-delete mirror; list/view show **`active`** as Active/Inactive badges (audit scaffold).
- **`enabled`** is the business toggle for share access — not the same as row `active`.

## 5. UI Behavior Requirements

- Hide **`company_id`** from list, view, and forms (`$hideCompanyIdTables` includes `company_module_share`).
- **`enabled`** on list/view renders ✅/❌ via `cr_render_cell_value()` (not raw `0`/`1`).
- **`active`** on list/view uses badge-success / badge-danger Active/Inactive (no emoji).
- Standard flattened CRUD: search, sort, pagination, bulk delete when `$totalRows >= $perPage`, `data-itm-db-import-endpoint="index.php"`, Actions column markers.
- Wrappers (`view.php`, `edit.php`, `list_all.php`) set `$crud_action` and `require 'index.php'`.
- **`index.php`** also loads `itm_crud_record_share.php` for AJAX share helpers on this slug.

## 6. API Actions (If Applicable)

- **`import_excel_rows`** — JSON POST on `index.php` (flattened CRUD import).
- Share session AJAX is handled via `itm_crud_record_share_handle_ajax_request()` when wired from index.

## 7. File Structure

- **index.php** — list, view, edit, import, and shared CRUD logic
- **create.php** — create form (duplicated helpers including `cr_render_cell_value`)
- **delete.php** — soft-delete / bulk / clear-table handler
- **view.php**, **edit.php**, **list_all.php** — thin wrappers → `index.php`
- **join.php** — QR share join entry when applicable

## 8. Multi-Tenant Rules

- All queries scope by session **`company_id`** when the table has `company_id`.
- Hide `company_id` in UI; server stamps on create/import.

## 9. Audit Logging Requirements

- **`trg_company_module_share_audit_insert|update|delete`** in `db/03_triggers.sql` — unconditional `audit_logs` on DML.

## 10. Common Pitfalls

- Do not confuse this folder with **`modules/share_modules/`** (matrix UI) or **`modules/company_module_access/`** (module enable/disable matrix).
- Do not render **`enabled`** as Active/Inactive badges — use ✅/❌ on list/view only.
- **`module_id`** must show registry module name in list/view (FK label), not raw id.

## 11. Examples of Safe Code Patterns

```php
$stmt = $conn->prepare(
    'SELECT id, module_id, enabled FROM company_module_share WHERE company_id = ? AND deleted_at IS NULL'
);
$stmt->bind_param('i', $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)

- Related matrix: **`modules/share_modules/AGENT_NOTES.md`**
- Regression: `php scripts/verify_module_share.php` — [verify_module_share.php?run=1](http://localhost/it-management/scripts/verify_module_share.php?run=1) (Admin session, open in a new browser tab)
- List UI: [modules/company_module_share/index.php](http://localhost/it-management/modules/company_module_share/index.php) (Admin session, open in a new browser tab)
