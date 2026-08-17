# Workflow Automation Rules

Tenant-scoped workflow rules that react to ticket and equipment events, evaluate simple JSON conditions, execute actions, and log each run to `automation_rule_runs`.

## Tables

| Table | Purpose |
|-------|---------|
| `automation_rules` | Rule definition: name, `trigger_slug`, `conditions_json`, `actions_json`, `enabled`, `last_run_at`, standard audit columns |
| `automation_rule_runs` | Per-run log: `rule_id`, `status` (`pending` / `success` / `failed` / `skipped`), `message`, `context_json`, `ran_at` |

Canonical DDL: `db/01_schema.sql`. Existing DBs: `db/migrations/automation_rules.sql` (destructive `DROP` + `CREATE`).

## Triggers

| Slug | When fired |
|------|------------|
| `ticket.created` | After successful ticket create in `modules/tickets/create.php` |
| `ticket.status_changed` | After ticket edit when `status_id` changes |
| `equipment.warranty_expiring` | Daily cron via `scripts/run_automation_rules.php` (warranty within 30 days) |

## Conditions JSON

Array of objects. Supported operators: `equals` (case-insensitive string match).

```json
[
  {"field": "status_name", "op": "equals", "value": "Open"}
]
```

Context fields depend on trigger (ticket rows include `ticket_id`, `title`, `status_name`, etc.; equipment rows include `equipment_id`, `hostname`, `warranty_expiry`).

Empty array `[]` matches all events for that trigger.

## Actions JSON

| `type` | Fields |
|--------|--------|
| `notify_employee` | `employee_id`, `title`, `body`, optional `action_url` |
| `send_email` | `to_email`, `subject`, `body` (HTML) |
| `set_ticket_status` | `status_id` or `status_name` (updates ticket; may chain `ticket.status_changed` with depth guard) |

Example:

```json
[
  {
    "type": "notify_employee",
    "employee_id": 1,
    "title": "New ticket",
    "body": "A ticket was created."
  }
]
```

## Core helper

`includes/itm_automation_rules.php`:

- `itm_automation_rules_trigger_slugs()`
- `itm_automation_rules_dispatch($conn, $companyId, $triggerSlug, $context)` — max 20 rules per dispatch; skips when `automation_depth > 2`
- `itm_automation_rules_run_scheduled($conn)` — date-based triggers
- `itm_automation_rules_build_ticket_context()` / `itm_automation_rules_resolve_ticket_status_name()`

Loaded from `config/config.php`.

## Admin UI

Module: [modules/automation_rules/index.php](http://localhost/it-management/modules/automation_rules/index.php) (Admin session).

Create/edit: name, trigger select, conditions/actions JSON textareas, enabled checkbox. View shows rule fields plus the last 50 run log rows.

## Cron

```bash
php scripts/run_automation_rules.php
```

Schedule daily for warranty-expiry rules.

## Regression

```bash
php scripts/verify_automation_rules.php
```

Browser: [verify_automation_rules.php?run=1](http://localhost/it-management/scripts/verify_automation_rules.php?run=1)

## Audit

`db/03_triggers.sql` defines `trg_automation_rules_audit_*` and `trg_automation_rule_runs_audit_*` (insert/update/delete → `audit_logs`).
