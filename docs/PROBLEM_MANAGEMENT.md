# Problem Management and Known Error Database

ITIL-style problem records, incident ticket linking, known-error workarounds, and knowledge-base publishing for recurring support issues.

## Tables

| Table | Purpose |
|-------|---------|
| `problems` | Investigation record: title, description, root_cause, status, owner, optional `knowledge_base_id` |
| `problem_ticket_links` | Many-to-many links to live incident tickets (not merge) |
| `known_errors` | Published workaround + optional `symptom_keywords` for matching |

Canonical DDL: `db/01_schema.sql`. Existing DBs: `db/migrations/problem_management.sql` (destructive — back up first).

## Status workflow

| Status | Meaning |
|--------|---------|
| `investigating` | Default; root cause analysis in progress |
| `known_error` | Workaround published in `known_errors` |
| `resolved` | Permanent fix applied |
| `closed` | No further action |

Helpers: `itm_problem_status_badge()`, `itm_problem_transition_status()` in `includes/itm_problem_management.php`.

## Ticket linking vs merge

- **Link incident:** keeps both tickets live; adds `problem_ticket_links` row and `ticket_activity` event `problem_linked`.
- **Merge ticket:** moves comments to target ticket and soft-archives source (`includes/itm_ticket_merge.php`).

## Known error publish

From [modules/problems/view.php](http://localhost/it-management/modules/problems/view.php) (Admin session — open in a new browser tab):

1. Enter workaround (+ optional symptom keywords).
2. Optional **Create KB article** writes/updates `knowledge_base` with category `Known Errors`.
3. Sets problem `status = known_error` and dispatches `known_error.published` automation/webhook.

## Ticket UI

- **View:** Related problems card, link picker, create-from-ticket, suggested known errors when no links.
- **Create:** Debounced fetch to `modules/problems/api.php?action=suggest` inserts workaround into description.

## Chatbot

After KB article search, `chat_api.php` calls `itm_known_error_search_for_query()` and appends workaround excerpts (tenant-scoped, prepared statements).

## Automation triggers

| Slug | When |
|------|------|
| `problem.created` | After problem insert |
| `problem.status_changed` | Status transition |
| `known_error.published` | Known error upsert |

See `docs/AUTOMATION_RULES.md` and `docs/INTEGRATION_WEBHOOKS.md`.

## Reports Hub

`get_problem_management_summary()` in `modules/reports/api/helpers.php` — doughnut chart on [modules/reports/index.php](http://localhost/it-management/modules/reports/index.php).

## Regression

```bash
php scripts/verify_problem_management.php
php scripts/verify_chatbot.php
```

Browser: [verify_problem_management.php?run=1](http://localhost/it-management/scripts/verify_problem_management.php?run=1) (Admin session).

Module UI: [modules/problems/index.php](http://localhost/it-management/modules/problems/index.php) (open in a new browser tab).
