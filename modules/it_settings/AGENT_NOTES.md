# IT Settings Module

## 1. Purpose
Manage IT department contact information, hours, and escalation rules for the organization.

## 2. Table(s)
- `it_settings`

## 3. Foreign Keys & Relations
- `company_id` -> `companies.id` (CASCADE)

## 4. Protection Zone
- None

## 5. Multi-tenant Scoping
- Strictly scoped by `company_id`.
- Only one record per company is expected (UNIQUE constraint on `company_id`).

## 6. Business Rules
- This information is used by the IT Support Chatbot for providing contact details and escalation instructions.

## 7. UI / Layout
- Follows the standard CRUD pattern using wrappers that require `modules/departments/index.php` or `modules/departments/create.php`.

## 8. API & AJAX
- None (standard CRUD).

## 9. Search & Filtering
- Standard text search.

## 10. Audit Coverage
- Triggers `trg_it_settings_audit_*` handle audit logging.

## 11. Known Pitfalls
- None.

## 12. References
- `AGENTS.md`
