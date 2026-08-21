# AGENT_NOTES.md - Master Tickets

## 1. Module Purpose

**Global** cross-company incident rollup for major problems. The `master_tickets` table has **no `company_id`** — tenant visibility and ACL come from linked `problems` rows and `employee_companies` / admin access (`itm_master_ticket_allowed_company_ids()`).

Create flow picks an **existing problem** that already has ≥1 linked incident (`problem_ticket_links`); `itm_problem_create_master_ticket()` copies fields and syncs all incidents.

## 2. Key Tables

- **master_tickets** — global rollup (`title`, `description`, `root_cause`, `summary_json`); no `company_id`
- **master_ticket_updates** — append-only history (system-derived; no audit triggers)
- **problems** — links via `master_ticket_id` (tenant-scoped)
- **problem_ticket_links** — problem ↔ incident tickets
- **tickets** — synced incident rows (read-only in this module)

## 3. Required Relationships

- **problems** → **master_tickets** (`master_ticket_id`, `ON DELETE SET NULL`)
- **master_ticket_updates** → **master_tickets** (`ON DELETE CASCADE`)
- **problem_ticket_links** → **problems** / **tickets**

## 4. Business Rules (Critical for Agents)

- Do **not** add `company_id` to `master_tickets` or use flattened departments scaffold.
- List/view visibility: user must have access to at least one linked problem's `company_id`.
- Create: eligible problems = `master_ticket_id` IS NULL, ≥1 live `problem_ticket_links`, company in allowed set.
- Mutations reuse **`includes/itm_master_ticket.php`** and **`itm_problem_create_master_ticket()`** — do not duplicate sync logic.
- Edit/attach requires `itm_master_ticket_can_manage()` (problem in session company or admin / `employee_companies`).

## 5. UI Behavior Requirements

- List/search/sort/pagination delegate to **`itm_master_ticket_list_page()`** in `includes/itm_master_ticket.php` (UI contract audit recognizes this helper in `scripts/lib/itm_ui_list_contract_checks.php`).
- **No `company_id` column** in list/view — show company count from linked problems instead.
- Tables opt out of table-tools import/export (`data-itm-no-import-excel`, `data-itm-no-export-*`).
- Actions: emoji-only + `title` / `aria-label` (NO MIXED).
- Problem Management still exposes the same master card on `view.php#master-ticket`; this module is the global entry point.

## 6. API Actions (If Applicable)

None — use Problem Management `api.php` for known-error suggest only.

## 7. File Structure

- **index.php** — global list (search, sort, pagination)
- **create.php** — pick existing major problem → create master
- **view.php** — detail, edit, attach problem, incidents, history
- **index.html** — directory guard

## 8. Multi-Tenant Rules

- Session `company_id` drives RBAC (`master_tickets` module slug) but **not** row storage on `master_tickets`.
- Cross-company list filtered by `itm_master_ticket_allowed_company_ids()`.
- Attach problem form uses explicit `company_id` + `problem_id` (must be in allowed companies).

## 9. Audit Logging Requirements

- **master_tickets** — `trg_master_tickets_audit_*` in `db/03_triggers.sql`
- **master_ticket_updates** — intentionally no audit triggers (append-only cache)

## 10. Common Pitfalls

- Do not scaffold `modules/master_tickets/` from `departments/` — wrong tenant model.
- Create without linked incidents fails by design (`itm_problem_create_master_ticket`).
- `compare_database_sql_modules.php` may still flag `master_ticket_updates` (no module — by design).

## 11. Examples of Safe Code Patterns

```php
$allowed = itm_master_ticket_allowed_company_ids($conn, (int)$_SESSION['employee_id']);
if (!itm_master_ticket_user_can_view($conn, $masterId, $allowed)) {
    // 404 / forbidden
}
```

## 12. Module Owner Notes (Optional)

- Sidebar: Management → Master Tickets (`includes/ui_config.php`).
- Registry: `modules_registry.module_slug = master_tickets`.
- Helpers: `includes/itm_master_ticket.php` (`itm_master_ticket_list_page`, `itm_master_ticket_list_eligible_problems`).
- Manual: [Open modules/master_tickets/index.php](http://localhost/it-management/modules/master_tickets/index.php) (Admin session).
