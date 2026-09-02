# AGENT_NOTES.md - API Key Scopes

## 1. Module Purpose

Stores granted API v2 scope slugs per employee API key (`ui_configuration` row). Managed from Settings → API Access for day-to-day use; this CRUD module is for inspection and admin maintenance.

## 2. Key Tables

- **api_key_scopes** — `company_id`, `ui_configuration_id`, `scope_slug`, standard audit columns.
- **ui_configuration** — per-employee Settings / API key profile (FK parent).
- **employees** — resolved for human-readable API key owner labels.

## 3. Required Relationships

- **api_key_scopes** → **ui_configuration** (`ui_configuration_id`, `ON DELETE CASCADE`).
- **api_key_scopes** → **companies** (`company_id`, `ON DELETE CASCADE`).
- Scope catalog: `includes/itm_api_v2_scopes.php` (`itm_api_v2_scope_catalog()`).

## 4. Business Rules (Critical for Agents)

- Unique per tenant: `(company_id, ui_configuration_id, scope_slug)`.
- `scope_slug` must be a key from `itm_api_v2_scope_catalog()` (e.g. `tickets.read`, `equipment.write`).
- `company_id` is hidden in list/view/forms but still stamped server-side on save.

## 5. UI Behavior Requirements

- **List/view:** `ui_configuration_id` shows **API Key Owner** as `Employee Name (Tier)` — not raw ids (`includes/fk_dropdown_helpers.php`).
- **List/view:** `scope_slug` shows catalog labels (e.g. `Tickets — read`), not raw slugs.
- **Forms:** `scope_slug` is a catalog `<select>`; `ui_configuration_id` dropdown lists tenant `ui_configuration` rows by employee name + tier (no quick-add for `ui_configuration`).
- Standard flattened CRUD: search, sort, pagination, bulk delete when `$totalRows >= $perPage`, hidden audit columns on list.

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php`; resolves owner labels and scope catalog labels on import.

## 7. File Structure

- **index.php** — list, view, edit, import (edit/view/list_all wrappers require this file).
- **create.php** — standalone create entry with duplicated CRUD helpers.
- **delete.php** — soft-delete handler.

## 8. Multi-Tenant Rules

- All queries filter by session `company_id`.
- FK label search joins `ui_configuration` → `employees` for owner name/tier (`includes/itm_crud_fk_label_search.php`).

## 9. Audit Logging Requirements

- `trg_api_key_scopes_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Do not show raw `ui_configuration_id` — table has no `name` column; always resolve via employee + tier.
- Do not allow quick-add on `ui_configuration` FK — rows are created via Settings / seed, not inline CRUD add.

## 11. Examples of Safe Code Patterns

```php
$label = itm_fk_ui_configuration_label_by_id($conn, (int)$company_id, (int)$uiConfigurationId);
```

## 12. Module Owner Notes (Optional)

- Canonical API v2 doc: `docs/API_V2.md`.
- Settings UI: `modules/settings/AGENT_NOTES.md`.
