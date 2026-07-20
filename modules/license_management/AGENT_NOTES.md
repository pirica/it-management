# AGENT_NOTES.md - License Management

## 1. Module Purpose
Tracks software licenses per company: name, key, type, quantity, supplier, purchase/expiry dates, price, active flag, and notes.

## 2. Key Tables
- **license_management** — main license records (CRUD module table).
- **license_types** — seed-only lookup for the **Type** dropdown (`Per User`, `Per Device`, `Enterprise`, `Subscription`, `Other`).

## 3. Required Relationships
- **license_management** → **companies** (`company_id`, CASCADE).
- **license_management** → **license_types** (`license_type_id`, RESTRICT).
- **license_management** → **suppliers** (`supplier_id`, SET NULL on delete).
- **license_types** → **companies** (`company_id`, CASCADE).

## 4. Business Rules (Critical for Agents)
- **Name required** on create/edit (`name` NOT NULL).
- **Quantity** defaults to **1** when omitted on create/save.
- **Price** accepts `.` as decimal separator; **comma is converted to dot** on POST (`cr_normalize_price_input()`).
- **Dates** stored as MySQL `DATE`; list/view/import use **dd/mm/yyyy** via `itm_format_cell_scalar_display()` / `itm_parse_date_input()`.
- **Type** values come from tenant-scoped `license_types` rows (seeded in `db/03_triggers.sql` + cross-company `INSERT IGNORE` replication). Quick-add on the Type select inserts via `select_options_api.php` (`license_types` whitelist); full CRUD is under **`modules/license_types/`** with **company_id** hidden.
- **Active** uses the standard checkbox double-label pattern on forms and badges on list/view.

## 5. UI Behavior Requirements
- Standard flattened CRUD duplicated from **departments** scaffold: bulk delete, search, pagination, Excel import/export, empty-state sample data.
- **company_id** hidden in list/create/edit/view.
- **FK labels:** list/view must show Type and Supplier names (not raw IDs) via `cr_fk_label_by_id()` / `itm_fk_label_by_id()`.
- Form field order: Name, License Key, Type, Quantity, Supplier, Purchase Date, Expiry Date, Price, Active, Notes.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON import on `index.php` (`data-itm-db-import-endpoint="index.php"`).

## 7. File Structure
- Flat CRUD: `index.php` (primary handler), thin wrappers `create.php`, `edit.php`, `view.php`, `list_all.php`, standalone `delete.php`.

## 8. Multi-Tenant Rules
- All queries scoped by `company_id`.
- FK dropdown options filtered by active company.

## 9. Audit Logging Requirements
- Database triggers: `trg_license_management_audit_*`, `trg_license_types_audit_*` (when lookup rows are quick-added).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **`modules/license_types/`** — lookup CRUD; keep **`company_id`** in `$hideCompanyIdTables` on every duplicated entry file (`index.php`, `edit.php`, `view.php`, `list_all.php`). [Cursor-Valid]
- **`license_management` seeds in `db/03_triggers.sql`** are declared **after** `suppliers` so FK parents exist before sample INSERTs (one sample row per company, companies 1–5). [Cursor-Valid]
- **`license_types` seeds** — five lookup rows per company (companies 1–5) plus cross-company `INSERT IGNORE` replication in `db/03_triggers.sql`. [Cursor-Valid]
- **Do not use `employees.active`**-style filters elsewhere; unrelated but same class of bug as equipment assignee dropdown. [Cursor-Invalid]
- **Deleting a `license_types` row** referenced by `license_management` fails (RESTRICT FK). [Cursor-Valid]
- **Price import:** normalise comma decimals before `cr_validate_numeric_value()`. [Cursor-Fixed]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = mysqli_prepare($conn, 'SELECT * FROM license_management WHERE company_id = ? ORDER BY name');
mysqli_stmt_bind_param($stmt, 'i', $companyId);
mysqli_stmt_execute($stmt);
```

### Safe INSERT
```php
$stmt = mysqli_prepare($conn, 'INSERT INTO license_management (company_id, name, license_type_id, quantity, active) VALUES (?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'isiii', $companyId, $name, $typeId, $quantity, $active);
mysqli_stmt_execute($stmt);
```

## 12. Module Owner Notes (Optional)
Sample seed row per company: Microsoft 365 E3 (`license_types.name = Per User`, supplier from tenant `suppliers` seed).
Authoritative cross-module rules: **`AGENTS.md` → License Management (mandatory)**. README screenshots: `docs/readme/license_management.png`, `docs/readme/demo_license_management.png`. MBQA: `php scripts/module_browser_qa_runner.php --module=license_management --company=1` (see **`scripts/SCRIPTS.md`**).
