# AGENT_NOTES.md - Expenses

## 1. Module Purpose
Tracks actual financial expenditures against budgets.

## 2. Key Tables
- **expenses** — stores individual expense records.

## 3. Required Relationships
- **expenses** → depends on **companies**.
- **expenses** → depends on **cost_centers**.
- **expenses** → depends on **gl_accounts**.

## 4. Business Rules (Critical for Agents)
- **Decimal Precision**: Amounts must be handled with 2-decimal precision.
- **Reporting Period**: Each expense has a `date` which determines which budget month/year it impacts.
- **RBAC (delete)**: POST delete handlers call `itm_require_role_module_permission(..., 'Expenses', 'delete')` before CSRF/delete SQL so read-only roles cannot bypass UI-hidden delete buttons.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

- **Formatted Currency**: Display amounts with currency symbols/formatting.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_expenses_audit_insert`, `trg_expenses_audit_update`, `trg_expenses_audit_delete` on `expenses` in `db/03_triggers.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Cursor-Valid]
- Unique per `company_id` + `cost_center_id` in schema — verify before bulk delete. [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM expenses WHERE company_id = ? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $companyId, $startDate, $endDate);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO expenses (company_id, cost_center_id, gl_account_id, date, amount, active) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiisdi", $companyId, $costCenterId, $glAccountId, $date, $amount, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Critical for generating budget vs. actual reports.
