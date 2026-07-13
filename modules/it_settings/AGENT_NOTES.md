# IT Settings Module

## 1. Purpose
Manage IT department contact information, hours, and escalation rules for the organization.

## 2. Table(s)
- `it_settings` (with columns `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, and `updated_at` defined as `INVISIBLE` metadata/tracking columns)

## 3. Foreign Keys & Relations
- `company_id` -> `companies.id` (CASCADE)
- `created_by` -> `employees.id` (implicit tracking column)
- `updated_by` -> `employees.id` (implicit tracking column)
- `deleted_by` -> `employees.id` (implicit tracking column)

## 4. Protection Zone
- None

## 5. Multi-tenant Scoping
- Strictly scoped by `company_id`.
- Only one record per company is expected (UNIQUE constraint on `company_id`).

## 6. Business Rules
- This information is used by the IT Support Chatbot for providing contact details and escalation instructions.

## 7. UI / Layout
- Standard flattened CRUD module (independent). Action wrappers (`create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) set `$crud_action` and invoke `index.php` for consolidated logic.

## 8. API & AJAX
- None (standard CRUD).

## 9. Search & Filtering
- Standard text search.

## 10. Audit Coverage
- Triggers `trg_it_settings_audit_insert`, `trg_it_settings_audit_update`, and `trg_it_settings_audit_delete` handle audit logging, capturing all visible and `INVISIBLE` metadata/tracking columns in JSON payloads.

## 11. Known Pitfalls
- `INVISIBLE` columns are hidden from standard `SELECT *` wildcard queries but remain discoverable via `DESCRIBE` and `SHOW COLUMNS`. The module controller filters them out from manageable fields to prevent rendering empty inputs in forms.

## 12. References
- `AGENTS.md`
