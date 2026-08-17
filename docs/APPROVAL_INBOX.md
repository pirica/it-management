# Approval Inbox

Unified tenant-scoped list of pending approval stages mirrored from source modules. Source records remain authoritative; inbox rows are upserted when workflows change.

## Table

`approval_inbox_items` — one row per `(company_id, module_slug, record_id, approval_stage)` with assignee, requester, status (`pending` / `approved` / `rejected` / `cancelled`), optional `due_at`, `action_url`, and `payload_json`.

## Helpers

`includes/itm_approval_inbox.php`:

- `itm_approval_inbox_adapter_slugs()` — registered sources (`request_password`, `employee_onboarding_requests`)
- `itm_approval_inbox_sync_module_record()` — rebuild inbox rows from a source record
- `itm_approval_inbox_upsert()` — insert/update a single stage row
- `itm_approval_inbox_fetch_for_assignee()` / `itm_approval_inbox_count_rows()` — list queries
- `itm_approval_inbox_decide()` — approve/reject from the inbox UI (updates source + inbox)

## Module UI

[Open modules/approval_inbox/index.php](http://localhost/it-management/modules/approval_inbox/index.php) — Admin session. Non-admins see assignee-scoped rows; admins see all company items. Inline ✅/❌ actions call `itm_approval_inbox_decide()`.

## Source wiring

| Module | Sync trigger |
|--------|----------------|
| `modules/request_password/` | After create/edit save, email approval send, and email `approval_api` decision |
| `modules/employee_onboarding_requests/` | After create/edit save, approval email send, and email `approval_api` decision |

## Regression

```bash
php scripts/verify_approval_inbox.php
```

Browser: [verify_approval_inbox.php?run=1](http://localhost/it-management/scripts/verify_approval_inbox.php?run=1) (Admin session).

## Migrations

- `db/migrations/approval_inbox.sql` — new table on existing databases
- Canonical DDL in `db/01_schema.sql`; audit triggers in `db/03_triggers.sql`
