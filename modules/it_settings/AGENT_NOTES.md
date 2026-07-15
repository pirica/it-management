# IT Settings Module

## 1. Purpose
Manage IT department contact information, hours, and escalation rules for the organization.

## 2. Table(s)
- `it_settings` (with columns `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, and `updated_at` metadata/tracking columns)

## 3. Foreign Keys & Relations
- `company_id` -> `companies.id` (CASCADE)
- `created_by` -> `employees.id` (implicit tracking column)
- `updated_by` -> `employees.id` (implicit tracking column)
- `deleted_by` -> `employees.id` (implicit tracking column)

## 4. Multi-tenant Scoping
- Strictly scoped by `company_id`.
- Only one record per company is expected (UNIQUE constraint on `company_id`).

## 5. Business Rules
- This information is used by the IT Support Chatbot for providing contact details and escalation instructions.

## 6. UI / Layout
- Standard flattened CRUD module (independent). Action wrappers (`create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) set `$crud_action` and invoke `index.php` for consolidated logic.

## 7. API & AJAX
- None (standard CRUD).

## 8. Search & Filtering
- Standard text search.

## 9. Audit Coverage
- Triggers `trg_it_settings_audit_insert`, `trg_it_settings_audit_update`, and `trg_it_settings_audit_delete` handle audit logging, capturing all visible metadata/tracking columns in JSON payloads.

## 10. Known Pitfalls


## 11. References
- `AGENTS.md`
