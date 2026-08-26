# IT Change Management

Tenant-scoped change requests linked to CMDB blast-radius, CAB approvals, calendar visibility, reminder cron, and automation/webhook events.

## Module

- [modules/change_requests/](http://localhost/it-management/modules/change_requests/index.php) — list, create/edit, view, soft-delete
- Helpers: `includes/itm_change_requests.php`
- Approval Inbox adapter slug: `change_requests` (parallel CAB stages `cab_{employee_id}`)

## Schema

| Table | Role |
|-------|------|
| `change_requests` | Header: `change_type`, `risk_level`, `rollback_plan`, optional `ticket_id`, schedule, status workflow |
| `change_request_configuration_items` | Blast-radius affected CIs |
| `change_request_cab_members` | Per-company CAB roster (seeded with tenant Admin) |
| `change_request_approvals` | Per-change CAB decisions |
| `change_request_settings` | `reminder_days_before` (default 1) |

Migration for existing DBs: `db/migrations/change_requests_itsm.sql`

## Workflow

1. Author creates change (draft) with source CI, risk, rollback plan, optional ticket link, schedule.
2. Submit → status `submitted`, CAB approval rows + Approval Inbox items for each CAB member.
3. CAB approves via Approval Inbox (or admin override on edit). Standard/normal changes need all CAB approvals; emergency needs one.
4. Any CAB rejection → `rejected`. Quorum met → `approved`.
5. Implementer marks `implemented` when done (admin or post-approval on edit form).

## Integrations

| Integration | Detail |
|-------------|--------|
| Calendar | `modules/calendar/index.php` shows scheduled submitted/approved/implemented changes |
| Reminders | `php scripts/run_change_request_reminders.php` — daily cron |
| Automation | `change.submitted`, `change.approved`, `change.rejected`, `change.status_changed`, `change.implemented` |
| Webhooks | Same event slugs via `itm_webhook_queue_enqueue()` |

## Regression

```bash
php scripts/verify_change_requests.php
php scripts/verify_cmdb.php
```

Browser: [verify_change_requests.php?run=1](http://localhost/it-management/scripts/verify_change_requests.php?run=1) (Admin session)
