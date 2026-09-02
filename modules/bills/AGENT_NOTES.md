# AGENT_NOTES.md - bills

## 1. Module Purpose
AP bill headers with line items on create/edit/view.

## 2. Key Tables
- **bills** — document header (`document_number`, totals, `paid_status_id`, `supplier_id`, `cost_center_id`, `gl_account_id`).
- **bill_line_items** — lines saved via `includes/itm_finance_document_lines.php` (no standalone CRUD module; not in `docs/list_soft-delete.txt` — parent `bills` is in scope).

## 4. Business Rules
- Line grid on edit/create; totals rolled up to header on save.
- **Attachments:** same contract as invoices (`includes/itm_finance_attachments.php`); storage folder uses bill `document_number` under `finance/{company_id}/bills/…`.
- **Post to expenses:** bill `view.php` → 📤 (title: Post to expenses) calls `itm_expenses_post_from_bill()` — copies header totals, bill `cost_center_id` / `gl_account_id`, **Posted** `paid_status_id`, sets `expenses.bill_id` and `expenses.invoice_number` = bill `document_number`; one expense per bill; requires Expenses **create** RBAC.

## 8. Multi-Tenant Rules
- `company_id` scoped; hidden in UI.
