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
- **Edit** lives on `view.php#master-edit` (not a separate `edit.php`). Saving title/description/root cause calls `itm_master_ticket_update()` → syncs every linked incident ticket **description** (canonical master block + local notes).
- **Broadcast message** on `view.php#master-edit`: **📨** form posts the same text as a **`ticket_comments`** row on every linked incident via `itm_master_ticket_broadcast_to_incidents()` (optional internal comment); history event `broadcast_to_tickets`; **Sent messages** table lists prior broadcasts.
- **Hard-delete actions** (edit permission + `itm_master_ticket_can_manage()`): **Incidents** — `unlink_master_incident` → `DELETE` `problem_ticket_links` row (ticket kept); **Linked problems** — `detach_master_problem` → `master_ticket_id = NULL` (problem kept); **Sent messages / Update History** — 🔎 view (`history_id` + `#master-history-view`), 🗑️ `delete_master_history` → `DELETE` `master_ticket_updates` (broadcast rows also hard-delete ticket comments via `meta_json.comments` or legacy `ticket_ids` + message body match).
- **Soft-delete master ticket** (delete RBAC + `itm_master_ticket_can_manage()`): list **🗑️** on [index.php](http://localhost/it-management/modules/master_tickets/index.php) and view toolbar post `master_action=delete_master_ticket` → `itm_master_ticket_soft_delete()` detaches all linked problems, restores local incident notes on tickets, sets `master_tickets.deleted_at` / `active=0`.
- **Attach problems:** multi-select eligible problems (`itm_master_ticket_list_eligible_problems` + `itm_master_ticket_attach_problems_bulk`).
- **Link incidents:** multi-select tickets from all allowed companies (`itm_master_ticket_list_linkable_tickets_for_master` + `itm_master_ticket_link_incidents_multi_company_bulk`); auto-creates a tenant problem on the master when missing (`itm_master_ticket_ensure_problem_for_company`), then links via `itm_problem_link_ticket`.

## 5. UI Behavior Requirements

- List/search/sort/pagination delegate to **`itm_master_ticket_list_page()`** in `includes/itm_master_ticket.php` (UI contract audit recognizes this helper in `scripts/lib/itm_ui_list_contract_checks.php`).
- **Add sample data:** when the list is empty and no live `master_tickets` rows exist, **Add sample data** creates one master ticket per seed company (1–5) the actor can access via `itm_master_ticket_seed_five_company_sample()` (problem + linked incident + master rollup per tenant).
- Sortable column headers use `text-decoration:none;color:inherit` (no blue link styling).
- **No `company_id` column** in list/view — show company count from linked problems instead.
- Tables opt out of table-tools import/export (`data-itm-no-import-excel`, `data-itm-no-export-*`).
- Actions: emoji-only + `title` / `aria-label` (NO MIXED).
- Problem Management still exposes the same master card on `view.php#master-ticket`; this module is the global entry point.
- Incident **View** links use [`modules/tickets/master_view.php`](http://localhost/it-management/modules/tickets/master_view.php) with `id` + `company_id` (not session-scoped `view.php`).

## 6. API Actions (If Applicable)

None — use Problem Management `api.php` for known-error suggest only.

## 7. File Structure

- **index.php** — global list (search, sort, pagination); **✏️** action links to `view.php#master-edit` when user has edit permission; **🗑️** soft-delete when delete permission + `itm_master_ticket_can_manage()`
- **create.php** — pick existing major problem → create master
- **view.php** — detail, inline edit (syncs all incidents on save), multi-select attach problems, multi-select link incidents to a linked problem, incidents table, history
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
