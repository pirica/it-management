# Knowledge Base Module

## 1. Purpose
Manage IT support articles, manuals, and procedures for the organization.

## 2. Table(s)
- `knowledge_base` (with columns `employee_id`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, and `updated_at` metadata/tracking columns)

## 3. Foreign Keys & Relations
- `company_id` -> `companies.id` (CASCADE)
- `employee_id` -> `employees.id` (CASCADE)
- `created_by` -> `employees.id`
- `updated_by` -> `employees.id`
- `deleted_by` -> `employees.id`

## 4. Multi-tenant Scoping
- Strictly scoped by `company_id`.

## 5. Business Rules
- Articles are visible to the IT Support Chatbot for diagnosing issues.
- Category field should be used to classify articles (e.g., Technical Documentation, Common Issues, etc.).
- `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` seeds company 1 via `INSERT … VALUES`; companies 2–5 copy rows with `INSERT … SELECT N, category, title, content, active FROM knowledge_base WHERE company_id = 1`. Add new seed articles with `php scripts/apply_module_sample_data_seed.php --module=knowledge_base --sample="Title:Body text"` (dry-run default; `--apply` writes `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` only).

## 6. UI / Layout
- Standard flattened CRUD module (independent).
- `employee_id`, `created_by`, `updated_by`, and `active` are automatically populated by the backend on database insertion and update.

## 7. API & AJAX
- `chat_api.php`: JSON endpoint for Chatbot search queries. Requires session and CSRF token.

## 8. Search & Filtering
- Standard text search on title and content.

## 9. Audit Coverage
- Triggers `trg_knowledge_base_audit_insert`, `trg_knowledge_base_audit_update`, and `trg_knowledge_base_audit_delete` handle audit logging, capturing columns in their JSON payloads.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]

- Always call `itm_api_enforce_rate_limit_or_exit()` and validate CSRF (`X-CSRF-Token`) on `chat_api.php`. [Cursor-Valid]
- Knowledge-base search must keep `AND company_id = ?` — never allow cross-tenant article leaks into the chatbot. [Cursor-Valid]
- Chatbot UI must HTML-escape replies (`escapeHtml`); escalate flows must read contact info from `it_settings`, not hardcode. [Cursor-Valid]
- **Named verifier:** `php scripts/verify_chatbot.php` (catalog: `scripts/scripts.php`) — rate limit, CSRF, tenant scope, `escapeHtml`, `enable_chatbot` gating.

## 11. References
- `AGENTS.md`
