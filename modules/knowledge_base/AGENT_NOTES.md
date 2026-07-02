# Knowledge Base Module

## 1. Purpose
Manage IT support articles, manuals, and procedures for the organization.

## 2. Table(s)
- `knowledge_base`

## 3. Foreign Keys & Relations
- `company_id` -> `companies.id` (CASCADE)
- `created_by` -> `employees.id`

## 4. Protection Zone
- None

## 5. Multi-tenant Scoping
- Strictly scoped by `company_id`.

## 6. Business Rules
- Articles are visible to the IT Support Chatbot for diagnosing issues.
- Category field should be used to classify articles (e.g., Technical Documentation, Common Issues, etc.).

## 7. UI / Layout
- Follows the standard CRUD pattern using wrappers that require `modules/departments/index.php` or `modules/departments/create.php`.

## 8. API & AJAX
- None (standard CRUD).

## 9. Search & Filtering
- Standard text search on title and content.

## 10. Audit Coverage
- Triggers `trg_knowledge_base_audit_*` handle audit logging.

## 11. Known Pitfalls
- None.

## 12. References
- `AGENTS.md`
