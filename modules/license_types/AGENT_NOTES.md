# AGENT_NOTES.md - License Types

## 1. Module Purpose
Tenant-scoped lookup for license categories used by **License Management** (`Per User`, `Per Device`, `Enterprise`, `Subscription`, `Other`, plus user-added types).

## 2. Key Tables
- **license_types** — `company_id`, `name`, `active`, standard timestamps.

## 3. Required Relationships
- **license_types** → **companies** (`company_id`, CASCADE).
- **license_management** → **license_types** (`license_type_id`, RESTRICT on delete).

## 4. Business Rules (Critical for Agents)
- **company_id** is set from the active session on create/save (hidden field); never expose **Company** in list/view/create/edit (`license_types` in `$hideCompanyIdTables` on every duplicated entry file).
- **active** defaults to **1** on create; forms use the checkbox double-label pattern; list/view use **Active** / **Inactive** badges (not raw `1`/`0`).
- **name** is required; rows referenced by **license_management** cannot be deleted (RESTRICT FK).

## 5. UI Behavior Requirements
- Standard flattened CRUD: bulk delete, search, pagination, Excel import/export when row count allows.
- **view.php** is a standalone duplicate (not an index wrapper) — keep `cr_render_cell_value()` active-badge logic aligned with **index.php**.

## 6. API Actions (If Applicable)
- **import_excel_rows** on `index.php` when table-tools save-to-database is used.

## 7. File Structure
- `index.php` (primary + create via `create.php` wrapper), standalone duplicates `edit.php`, `view.php`, `list_all.php`, `delete.php` wrapper.

## 8. Multi-Tenant Rules
- All queries and inserts scoped by `company_id`.

## 9. Audit Logging Requirements
- `trg_license_types_audit_insert|update|delete` on `license_types`.

## 10. Common Pitfalls
- **view.php** missing `license_types` in `$hideCompanyIdTables` or missing active badge in `cr_render_cell_value()` shows Company and raw Active `1`.
- Omitting `$name === 'active'` boolean handling on **index.php** create (schema uses `tinyint`, not always `tinyint(1)`) renders Active as a text field.


## 11. Examples of Safe Code Patterns

### Active badge (list/view)
```php
if ($field === 'active') {
    $isActive = ((int)$value === 1);
    return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">'
        . ($isActive ? 'Active' : 'Inactive') . '</span>';
}
```

## 12. Module Owner Notes (Optional)
Parent module: **`modules/license_management/`**. Type quick-add whitelist: **`license_types`** in `includes/itm_select_options_policy.php`.
