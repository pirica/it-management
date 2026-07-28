# AGENT_NOTES.md - invoices

## 1. Module Purpose
AR invoice headers with line items on create/edit/view.

## 2. Key Tables
- **invoices** — `document_number`, `customer_id` (optional FK to `customers`), `contact_name`, optional `supplier_id`, `cost_center_id`, `gl_account_id`, `amount_due` (payments via `finance_payment_allocations`).
- **invoice_line_items** — lines via `includes/itm_finance_document_lines.php` (no standalone module; not in `docs/list_soft-delete.txt` — parent `invoices` is in scope).

## 3. Entry files
- **Canonical UI:** `index.php` (line grid + payment allocations on edit).
- **Wrappers:** `view.php`, `edit.php`, `list_all.php` delegate to `index.php` (same pattern as `modules/bills/`).

## 4. Business Rules
- Line grid on edit/create; totals rolled up to header on save.
- **Attachments:** multi-file drag/drop + file picker on create/edit (`enctype="multipart/form-data"`); view lists downloads via `attachment.php`. Allowed: pdf, zip, jpg, png, bmp, xlsx, docx, xls, doc, txt (5 MB each). Storage: `finance/{company_id}/invoices/{document_number}/`.
- **Post to expenses:** invoice `view.php` → 📤 (title: Post to expenses) calls `itm_expenses_post_from_invoice()` — copies header totals, invoice `cost_center_id` / `gl_account_id`, **Posted** `paid_status_id`, sets `expenses.invoice_id` and `expenses.invoice_number` = invoice `document_number`; one expense per invoice; requires Expenses **create** RBAC.

## 8. Multi-Tenant Rules
- `company_id` scoped; hidden in UI.
