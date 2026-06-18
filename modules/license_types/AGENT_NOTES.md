# AGENT_NOTES.md - License Types

## 1. Module Purpose
Tenant-scoped lookup for license categories used by **License Management** (`Per User`, `Per Device`, `Enterprise`, `Subscription`, `Other`, plus user-added types).

## 2. Key Tables
- **license_types** — `company_id`, `name`, `active`, standard timestamps.

## 3. Required Relationships
- **license_types** → **companies** (`company_id`, CASCADE).
- **license_management** → **license_types** (`license_type_id`, RESTRICT on delete).

## 4. Business Rules (Critical for Agents)
- **company_id** is set from the active session on create/save (hidden field); never expose **Company** in list/view/create/edit.
- **name** is the primary label; **active** uses standard checkbox (forms) and badges (list/view).
- Rows referenced by **license_management** cannot be deleted (RESTRICT FK).
- Default seeds live in `database.sql` (five types per company) plus cross-company `INSERT IGNORE` replication.

## 5. UI Behavior Requirements
- Standard flattened CRUD (manufacturers scaffold): bulk delete, search, pagination, Excel import/export when row count allows.
- **company_id** hidden via `$hideCompanyIdTables` including `license_types`.
- Type dropdown quick-add from **License Management** inserts into this table via `modules/select_options_api.php` (whitelisted in `includes/itm_select_options_policy.php`).

## 6. API Actions (If Applicable)
- **import_excel_rows** on `index.php` when table-tools save-to-database is used.

## 7. File Structure
- Flat CRUD: `index.php`, wrappers `create.php`, `edit.php`, `view.php`, `list_all.php`, `delete.php`.

## 8. Multi-Tenant Rules
- All queries and inserts scoped by `company_id`.

## 9. Audit Logging Requirements
- `trg_license_types_audit_insert|update|delete` on `license_types`.

## 10. Common Pitfalls
- Omitting `license_types` from `$hideCompanyIdTables` shows raw **Company** column — match **warranty_types** pattern in all duplicated entry files.
- Removing `license_types` from the select-options whitelist breaks **License Management** Type quick-add (`This list cannot be updated from quick-add.`).

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = mysqli_prepare($conn, 'SELECT id, name, active FROM license_types WHERE company_id = ? ORDER BY name');
mysqli_stmt_bind_param($stmt, 'i', $companyId);
mysqli_stmt_execute($stmt);
```

## 12. Module Owner Notes (Optional)
Parent module: **`modules/license_management/`**. MBQA: `php scripts/module_browser_qa_runner.php --module=license_types --company=1`.
