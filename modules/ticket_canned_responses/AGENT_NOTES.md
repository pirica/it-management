# AGENT_NOTES — modules/ticket_canned_responses

Tenant-scoped canned comment snippets (`title`, `body`, optional `category_id`). Flattened CRUD; not in record-share slugs. Picker: Shift+F2 in ticket comments.

## Merge tags

- **`{{survey_url}}`** — resolved when the canned body is loaded in [modules/ticket_comments/index.php](http://localhost/it-management/modules/ticket_comments/index.php) for the active ticket (`itm_ticket_survey_merge_canned_body()` in `includes/itm_ticket_survey.php`). Uses the latest pending or completed survey token; empty when no survey exists.
- Seed example in `db/02_data.sql` (company 1): body contains `{{survey_url}}` for CSAT follow-up copy.

## Regression and docs

- `php scripts/verify_ticket_productivity.php` — canned CRUD + productivity pack.
- `php scripts/verify_ticket_surveys.php` — `{{survey_url}}` merge tag probe.
- `docs/TICKET_PRODUCTIVITY.md`, `docs/TICKET_SURVEYS.md`.
