# AGENT_NOTES.md — Approval Inbox

## 1. Module Purpose

Unified list of pending approval stages across wired source modules. Assignees (or admins) approve/reject from one inbox; source modules stay authoritative.

## 2. Key Tables

- **approval_inbox_items** — mirror rows keyed by `(company_id, module_slug, record_id, approval_stage)`

## 3. Required Relationships

- `company_id` → `companies`
- `requester_employee_id` / `assignee_employee_id` → `employees` (SET NULL on delete)
- Source records: `request_password`, `employee_onboarding_requests`

## 4. Business Rules (Critical for Agents)

- Non-admins: list scoped to `assignee_employee_id = session employee_id` (`mine_only`).
- Admins (`itm_is_admin()`): see all company rows.
- Decisions call `itm_approval_inbox_decide()` — updates source module fields then re-syncs inbox.
- Unique stage key prevents duplicate rows per approval step.
- Read-only module: wrappers route create/edit/delete/view/list_all to `index.php`.

## 5. UI Behavior Requirements

- Bespoke list in `index.php` (not flattened CRUD scaffold).
- Status filter, search, pagination via `itm_resolve_records_per_page()`.
- Table opts out of import/export (`data-itm-no-import-excel`, `data-itm-no-export-*`).
- Actions: open source record (🔎), pending rows show ✅ approve / ❌ reject.

## 6. API / Integration

- Helpers: `includes/itm_approval_inbox.php`
- Source sync: `itm_approval_inbox_sync_module_record($conn, $companyId, $moduleSlug, $recordId)`

## 7. Security & Tenant Rules

- Tenant scope: `company_id` on all queries.
- Assignee check on decide (admin override).

## 8. Audit Logging

- `trg_approval_inbox_items_audit_*` in `db/03_triggers.sql`

## 9. Regression / Verification

- `php scripts/verify_approval_inbox.php`
- Canonical doc: `docs/APPROVAL_INBOX.md`

## 10. Common Pitfalls

- Forgetting to call sync after source-module status changes leaves stale inbox rows. [Cursor-Fixed]
- Do not hard-delete inbox rows — use status `cancelled` via sync when source is soft-deleted.
