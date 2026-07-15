# AGENT_NOTES.md - Monthly Budgets

## 1. Module Purpose
Breaks down the annual budget into monthly allocations for finer financial control.

## 2. Key Tables
- **monthly_budgets** — stores monthly budget amounts.

## 3. Required Relationships
- **monthly_budgets** → depends on **companies**.
- **monthly_budgets** → depends on **annual_budgets** (via `annual_budget_id`).

## 4. Business Rules (Critical for Agents)
- **Unique Month per Year**: Only one record per company, annual budget link, and month (1-12).
- **Cascade**: Deleting an annual budget removes these monthly records.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

- **Currency Formatting**: Handle decimal amounts.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- Database triggers `trg_monthly_budgets_audit_insert`, `trg_monthly_budgets_audit_update`, `trg_monthly_budgets_audit_delete` on `monthly_budgets` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Valid]-[2026-07-15]
- Child of **annual_budgets** — unique per `company_id` + `annual_budget_id` + `month`. [Valid]-[2026-07-15]
- Respect tenant unique constraints; duplicates fail at the database layer. [Valid]-[2026-07-15]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM monthly_budgets WHERE annual_budget_id = ? AND month = ?");
$stmt->bind_param("ii", $annualBudgetId, $month);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO monthly_budgets (company_id, annual_budget_id, month, amount, active) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiidi", $companyId, $annualBudgetId, $month, $amount, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used for monthly financial performance tracking.
