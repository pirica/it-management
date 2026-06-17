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

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: POST handlers validate via `cr_require_valid_csrf_token()`; forms include hidden `csrf_token` from `itm_get_csrf_token()`.
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
- Database triggers `trg_expenses_audit_insert`, `trg_expenses_audit_update`, `trg_expenses_audit_delete` on `expenses` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first.
- Unique per `company_id` + `cost_center_id` in schema — verify before bulk delete.
- Respect tenant unique constraints; duplicates fail at the database layer.
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI.

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
