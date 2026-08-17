# AGENT_NOTES.md - SLA Command Center

## 1. Module Purpose

Proactive SLA breach dashboard for open tickets that have `sla_response_due_at` / `sla_resolve_due_at` stamped from `ticket_sla_policies`. Tabs: **At Risk** (&lt; 2h to next due), **Breached**, **Met**, **All SLA**. Companion cron `scripts/run_ticket_sla_monitor.php` stamps `sla_*_breached_at`, logs `ticket_activity`, and notifies assignees.

## 2. Key Tables

- **tickets** — SLA due/breach timestamps (`sla_response_due_at`, `sla_resolve_due_at`, `sla_response_breached_at`, `sla_resolve_breached_at`, `first_response_at`, `resolved_at`)
- **ticket_sla_policies** — per-priority response/resolve minute targets (configured in `modules/ticket_sla_policies/`)
- **ticket_sla_escalation_rules** — priority + breach type → `escalate_to_employee_id` (admin UI on dashboard)
- **ticket_activity** — breach events (`sla_response_breached`, `sla_resolve_breached`)

## 3. Required Relationships

- **tickets** → `ticket_priorities`, `ticket_statuses`, `employees` (assignee) for list labels
- SLA due dates set on create via `itm_ticket_sla_apply_on_create()` in `includes/itm_ticket_sla.php`

## 4. Business Rules (Critical for Agents)

- Tenant scope: `company_id` session; list only `deleted_at IS NULL`, `is_archived = 0`, with at least one SLA due column set.
- **Breached:** stamped column set OR past due without `first_response_at` / `resolved_at`.
- **At risk:** not breached; next open milestone due within 2 hours (calendar / 24×7).
- **Met:** not breached/at risk; response and resolve milestones satisfied when applicable.
- Cron uses `itm_ticket_sla_process_scheduled_breaches()` — idempotent breach stamps + `itm_notify_employee()` to assignee + escalation reassignment via `itm_ticket_sla_apply_escalation_for_breach()`.
- First agent comment on a ticket should call `itm_ticket_sla_stamp_first_response()` (`modules/ticket_comments/`).

## 5. UI Behavior Requirements

- Bespoke dashboard (`index.php`) — not flattened scaffold CRUD.
- Tabs with server-rendered list + optional JSON poll (`api.php?action=summary` every 120s).
- Ticket list/view in `modules/tickets/` show SLA badge via `itm_ticket_sla_render_badge()` (green/amber/red).
- Emoji-only action buttons per `AGENTS.md` NO MIXED rules.

## 6. API Endpoints

- `modules/ticket_sla_dashboard/api.php`
  - `GET ?action=summary` — `{ at_risk, breached, met, total }`
  - `GET ?action=list&filter=at_risk|breached|met|all&page=&per_page=`
- Rate limit: `itm_api_enforce_rate_limit_or_exit()`

## 7. File Structure

- `index.php` — tabbed dashboard UI
- `api.php` — JSON summary + list
- `index.html` — directory listing guard

## 8. Scripts / Regression

- `php scripts/run_ticket_sla_monitor.php` — cron (every 15 min recommended)
- `php scripts/verify_ticket_sla_dashboard.php` — helper + API contract checks

## 9. Sidebar

- `includes/ui_config.php` — Management section after Ticket SLA Policies (`ticket_sla_dashboard`, 📊 SLA Command Center)

## 10. Documentation

- Canonical: `docs/TICKET_SLA_DASHBOARD.md`

## 11. Common Pitfalls

- Migration `db/migrations/ticket_sla_breach.sql` is destructive (`DROP TABLE tickets`) — back up before apply on production.
- Without cron, breach columns stay NULL until monitor runs; badges still show live overdue via predicate SQL.
- Tickets without a matching `ticket_sla_policies` row have no SLA due dates and are excluded from the dashboard.
