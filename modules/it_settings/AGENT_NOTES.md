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

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]

- `UNIQUE(company_id)` — one row per tenant; a second insert fails. [Cursor-Valid]
- Chatbot escalation reads this table — empty or inactive rows break escalate contact copy. [Cursor-Valid]
- Do not invent a second “default” IT settings row per company. [Cursor-Valid]

## 11. References
- `AGENTS.md`
