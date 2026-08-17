# AGENT_NOTES.md - Automation Rules

## 1. Module Purpose

Tenant-scoped workflow automation: admins define rules with a trigger, JSON conditions, and JSON actions. The dispatcher runs on ticket hooks and a scheduled cron for warranty expiry.

## 2. Key Tables

- **automation_rules** — rule definition (`trigger_slug`, `conditions_json`, `actions_json`, `enabled`, `last_run_at`)
- **automation_rule_runs** — execution log per rule (`status`, `message`, `context_json`, `ran_at`)

## 3. Required Relationships

- **automation_rules** → `companies` (`company_id`, ON DELETE CASCADE)
- **automation_rule_runs** → `automation_rules` (`rule_id`, ON DELETE CASCADE), `companies` (`company_id`)

## 4. Business Rules (Critical for Agents)

- `company_id` hidden in all UI views; scoped by session tenant.
- `enabled` controls dispatch; row `active` follows soft-delete scaffold (`deleted_at IS NULL` on list).
- `last_run_at` is system-stamped on dispatch attempts — not editable in forms.
- Loop guard: max 20 rules per dispatch; skip when `automation_depth > 2` in context.
- `set_ticket_status` action updates ticket `status_id` and may chain `ticket.status_changed` with incremented `automation_depth` (loop guard at depth &gt; 2).

## 5. UI Behavior Requirements

- Flat CRUD: `index.php` (list + handlers), wrappers `create.php`, `edit.php`, `view.php`, `list_all.php`, `delete.php`.
- Create/edit: simplified form — name, trigger select, conditions/actions textareas, enabled checkbox.
- List hides `conditions_json` and `actions_json` columns; view shows full fields plus last 50 runs.
- Standard scaffold: bulk delete when rows ≥ `records_per_page`, search, pagination, audit columns on view.

## 6. API Actions

- No public API module; dispatcher is PHP-only (`itm_automation_rules_dispatch`, `itm_automation_rules_run_scheduled`).

## 7. File Layout

- `includes/itm_automation_rules.php` — trigger catalog, condition evaluation, action execution, logging.
- `scripts/run_automation_rules.php` — cron runner for date-based triggers.
- `scripts/verify_automation_rules.php` — regression (seed rule, dispatch, run log).
- `docs/AUTOMATION_RULES.md` — canonical JSON examples and commands.

## 8. Hooks

- `modules/tickets/create.php` — `ticket.created` on create; `ticket.status_changed` when `status_id` changes on edit.

## 9. Regression

```bash
php scripts/verify_automation_rules.php
```

## 10. Known Pitfalls

- Invalid JSON in conditions/actions fails validation on save.
- Scheduled warranty trigger only runs when enabled rules exist for `equipment.warranty_expiring`.

## 11. Change Log

- Feature C workflow automation rules module and dispatcher.

## 12. Related Docs

- `docs/AUTOMATION_RULES.md`
