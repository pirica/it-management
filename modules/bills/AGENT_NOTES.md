# AGENT_NOTES.md - bills

## 1. Module Purpose
RootFi-aligned AP bill headers with line items on create/edit/view.

## 2. Key Tables
- **bills** — document header (`document_number`, totals, `paid_status_id`, `supplier_id`).
- **bill_line_items** — lines saved via `includes/itm_finance_document_lines.php` (no standalone CRUD module).

## 4. Business Rules
- **No platform sync:** RootFi webhooks are not in scope; optional `platform_id` / `platform_updated_at` / `platform_status` on headers are manual metadata only.
- Line grid on edit/create; totals rolled up to header on save.
- **Post to expenses:** bill `view.php` → 📤 (title: Post to expenses) calls `itm_expenses_post_from_bill()` — copies header totals, bill `cost_center_id` / `gl_account_id`, **Posted** `paid_status_id`, sets `expenses.bill_id`; one expense per bill; requires Expenses **create** RBAC.

## 8. Multi-Tenant Rules
- `company_id` scoped; hidden in UI.
