# Knowledge Base Module

## 1. Purpose
Manage IT support articles, manuals, and procedures for the organization.

## 2. Table(s)
- `knowledge_base` (with columns `employee_id`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, and `updated_at` defined as `INVISIBLE` metadata/tracking columns)

## 3. Foreign Keys & Relations
- `company_id` -> `companies.id` (CASCADE)
- `employee_id` -> `employees.id` (CASCADE)
- `created_by` -> `employees.id`
- `updated_by` -> `employees.id`
- `deleted_by` -> `employees.id`

## 4. Protection Zone
- None

## 5. Multi-tenant Scoping
- Strictly scoped by `company_id`.

## 6. Business Rules
- Articles are visible to the IT Support Chatbot for diagnosing issues.
- Category field should be used to classify articles (e.g., Technical Documentation, Common Issues, etc.).

## 7. UI / Layout
- Standard flattened CRUD module (independent).
- `INVISIBLE` tracking and audit columns are filtered out from manageable fields to prevent rendering them in forms.
- `employee_id`, `created_by`, `updated_by`, and `active` are automatically populated by the backend on database insertion and update.

## 8. API & AJAX
- `chat_api.php`: JSON endpoint for Chatbot search queries. Requires session and CSRF token.

## 9. Search & Filtering
- Standard text search on title and content.

## 10. Audit Coverage
- Triggers `trg_knowledge_base_audit_insert`, `trg_knowledge_base_audit_update`, and `trg_knowledge_base_audit_delete` handle audit logging, capturing both visible and `INVISIBLE` columns in their JSON payloads.

## 11. Known Pitfalls
- `INVISIBLE` columns are hidden from standard `SELECT *` wildcard queries but remain discoverable via `DESCRIBE` and explicit selects. The module controller filters them out from manageable fields to prevent rendering empty inputs in forms.

## 12. References
- `AGENTS.md`
