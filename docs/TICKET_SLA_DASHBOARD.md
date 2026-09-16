# Ticket SLA Command Center

Proactive SLA breach management for the IT Management System tickets module.

## Overview

| Piece | Location |
|-------|----------|
| Policy configuration | [modules/ticket_sla_policies/](http://localhost/it-management/modules/ticket_sla_policies/index.php) |
| Dashboard UI | [modules/ticket_sla_dashboard/index.php](http://localhost/it-management/modules/ticket_sla_dashboard/index.php) |
| JSON API | [modules/ticket_sla_dashboard/api.php](http://localhost/it-management/modules/ticket_sla_dashboard/api.php) |
| Core helpers | `includes/itm_ticket_sla.php` |
| Cron monitor | [scripts/run_ticket_sla_monitor.php?run=1](http://localhost/it-management/scripts/run_ticket_sla_monitor.php?run=1) (Admin) |
| Regression | [scripts/verify_ticket_sla_dashboard.php?run=1](http://localhost/it-management/scripts/verify_ticket_sla_dashboard.php?run=1) |

## Workflow

1. Admin defines per-priority response/resolve minutes in **Ticket SLA Policies**.
2. On ticket **create**, `itm_ticket_sla_apply_on_create()` stamps `sla_response_due_at` and `sla_resolve_due_at`.
3. First **ticket comment** stamps `first_response_at` via `itm_ticket_sla_stamp_first_response()`.
4. **SLA Command Center** tabs:
   - **At Risk** — next open milestone due within 2 hours (calendar 24/7).
   - **Breached** — past due or `sla_*_breached_at` stamped.
   - **Met** — milestones satisfied within SLA windows.
   - **All SLA** — any open ticket with SLA due dates.
5. Cron `php scripts/run_ticket_sla_monitor.php` (every 15 min) calls `itm_ticket_sla_process_scheduled_breaches()` to:
   - Set `sla_response_breached_at` / `sla_resolve_breached_at`
   - Append `ticket_activity` events
   - Notify assignee via `itm_notify_employee()`
   - Apply **escalation rules** (`ticket_sla_escalation_rules`) — reassign ticket and notify `escalate_to_employee_id` per priority + breach type

## Escalation rules

Admins configure rules on the SLA Command Center dashboard ([modules/ticket_sla_dashboard/index.php](http://localhost/it-management/modules/ticket_sla_dashboard/index.php)):

| Field | Purpose |
|-------|---------|
| `priority_id` | Match ticket priority (NULL = any) |
| `breach_type` | `response` or `resolve` |
| `escalate_to_employee_id` | New assignee when breach is stamped |

Table: `ticket_sla_escalation_rules` (migration `db/migrations/ticket_sla_escalation_rules.sql`). Helpers: `itm_ticket_sla_list_escalation_rules()`, `itm_ticket_sla_save_escalation_rule()`, `itm_ticket_sla_apply_escalation_for_breach()`.

## Database

Columns on `tickets` (see `db/01_schema.sql`, migration `db/migrations/ticket_sla_breach.sql`):

| Column | Purpose |
|--------|---------|
| `sla_response_due_at` | First-response deadline |
| `sla_resolve_due_at` | Resolution deadline |
| `sla_response_breached_at` | When response SLA was breached (cron) |
| `sla_resolve_breached_at` | When resolve SLA was breached (cron) |
| `first_response_at` | First agent comment timestamp |
| `resolved_at` | Resolution timestamp |

## API

Session + rate limit required.

```
GET modules/ticket_sla_dashboard/api.php?action=summary
GET modules/ticket_sla_dashboard/api.php?action=list&filter=at_risk|breached|met|all&page=1&per_page=20
```

## UI badges

`itm_ticket_sla_render_badge($row)` on ticket list and view:

| State | Colour | Label |
|-------|--------|-------|
| Breached | Red | Breached |
| At risk | Amber | At risk (+ countdown) |
| Met / on track | Green | Met / On track |
| No SLA | Grey | — |

## Commands

```bash
php scripts/run_ticket_sla_monitor.php
php scripts/run_ticket_sla_monitor.php --company=1
php scripts/verify_ticket_sla_dashboard.php
php scripts/migrate.php --apply   # when ticket_sla_breach.sql or ticket_sla_escalation_rules.sql is pending
```

## Limitations

- Calendar-hour SLA (24/7); business-hours SLA is future work.
- Escalation runs once per breach stamp (idempotent via activity log guard).
