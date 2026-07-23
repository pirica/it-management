# AGENT_NOTES.md - Annual Budgets

## 1. Module Purpose
Manages annual financial budget allocations per Cost Center and GL Account for a specific fiscal year.

## 2. Key Tables
- **annual_budgets** — main budget records.

## 3. Required Relationships
- **annual_budgets** → depends on **companies**.
- **annual_budgets** → depends on **cost_centers**.
- **annual_budgets** → depends on **gl_accounts**.
- **annual_budgets** → referenced by **monthly_budgets**.

## 4. Business Rules (Critical for Agents)
- **Unique Constraint**: Only one budget record allowed per `company_id`, `cost_center_id`, `gl_account_id`, and `year`.
- **Persistence**: Deleting an annual budget will cascade delete associated monthly budgets.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Formatted Currency**: Amounts should be handled as decimals.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers (`trg_annual_budgets_audit_*`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Duplicate Budgets**: Attempting to insert a budget for a combination that already exists will trigger a unique key violation. [Cursor-Valid]
- **Cascade Deletes**: Be careful when deleting annual budgets as it removes the monthly breakdown. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM annual_budgets WHERE company_id = ? AND year = ?");
$stmt->bind_param("ii", $companyId, $year);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO annual_budgets (company_id, cost_center_id, gl_account_id, year, amount) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiid", $companyId, $costCenterId, $glAccountId, $year, $amount);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Ensure the year is valid (usually current or future).
## Share (temporary QR / code)
- **Capable:** `itm_qr_share_capable_module_slugs()`.
- **UI:** Share buttons on index.php inline view block.
- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.
- **Doc:** `docs/CRUD_RECORD_SHARE.md`.
