# AGENT_NOTES.md - Expenses

## 1. Module Purpose
Tracks actual financial expenditures against budgets.

## 2. Key Tables
- **expenses** — budget actuals (gross `amount` incl. VAT); AP header fields aligned with RootFi Bills.
- **tax_rates**, **paid_statuses**, **payment_modes** — tenant lookups (FK from expenses).
- **bills** / **bill_line_items** — optional source document (`expenses.bill_id`).

## 3. Required Relationships
- **expenses** → depends on **companies**.
- **expenses** → depends on **cost_centers**.
- **expenses** → depends on **gl_accounts**.
- **expenses** → **paid_statuses** (required); **tax_rates**, **payment_modes**, **suppliers**, **bills** (optional).

## 4. Business Rules (Critical for Agents)
- **External integration:** No RootFi sync webhooks or automated platform sync. `platform_*` fields on related finance tables are optional metadata only (see `db/AGENT_NOTES.md` → Finance tables).
- **Decimal Precision**: Amounts must be handled with 2-decimal precision.
- **Reporting Period**: **Budget report** uses `COALESCE(posting_date, date)` and only **Posted** + **Paid** `paid_status_id` rows (`itm_expenses_paid_status_ids_for_actuals()`).
- **Legacy `date`**: On save, synced from `posting_date` (fallback `invoice_date`) via `itm_expenses_ap_apply_post_normalization()`.
- **Currency**: Default **EUR** (`currency_code`, `exchange_rate` default 1).
- **Tax snapshot**: `tax_rate_snapshot` stamped from `tax_rates.rate_percent` on save.
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
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow). Headers: RootFi aliases (`posted date`, `document number` → `invoice_number`, `contact`/`supplier` → `supplier_id` by name); rows normalized via `itm_expenses_ap_normalize_import_row()` (EUR, Draft `paid_status_id`, posting/date sync, tax snapshot).

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
## Share (temporary QR / code)
- **Capable:** `itm_qr_share_capable_module_slugs()`.
- **UI:** Share buttons on index.php inline view block.
- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.
- **Doc:** `docs/CRUD_RECORD_SHARE.md`.
