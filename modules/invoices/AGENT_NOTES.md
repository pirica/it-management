# AGENT_NOTES.md - invoices

## 1. Module Purpose
RootFi-aligned AR invoice headers with line items on create/edit/view.

## 2. Key Tables
- **invoices** — `document_number`, `customer_id` (optional FK to `customers`), `contact_name`, `platform_contact_id`, optional `supplier_id`, `amount_due` (payments via `finance_payment_allocations`).
- **invoice_line_items** — lines via `includes/itm_finance_document_lines.php`.

## 3. Entry files
- **Canonical UI:** `index.php` (line grid + payment allocations on edit).
- **Wrappers:** `view.php`, `edit.php`, `list_all.php` delegate to `index.php` (same pattern as `modules/bills/`).

## 4. Business Rules
- **No platform sync:** RootFi webhooks are not in scope; optional `platform_id`, `platform_contact_id`, `platform_updated_at`, `platform_status` are manual metadata only.
- Does not feed budget_report in v1 (AR store only).

## 8. Multi-Tenant Rules
- `company_id` scoped; hidden in UI.
