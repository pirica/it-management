# Scheduled Executive Reports

Scheduled executive reports let administrators email Reports Hub datasets on a cron schedule.

## Tables

- `scheduled_reports` — per-tenant schedule (`report_slug`, five-field `schedule_cron`, `recipients_json`, `format` `pdf`|`xlsx`, `last_sent_at`, `enabled`, audit columns).

## UI

- [Reports Hub index](http://localhost/it-management/modules/reports/index.php) — **Admin session** required. Admins manage schedules via the modal on the hub page.

## Core helper

- `includes/itm_scheduled_reports.php` — catalog slugs, cron matcher, dataset loaders (via `modules/reports/api/helpers.php`), HTML/CSV attachments, `itm_scheduled_reports_process_due()`.

## Cron

```bash
php scripts/run_scheduled_reports.php
php scripts/run_scheduled_reports.php --company=1
```

Schedule in crontab (example: weekdays 08:00):

```
0 8 * * 1-5 cd /path/to/it-management && php scripts/run_scheduled_reports.php
```

## Verification

```bash
php scripts/verify_scheduled_reports.php
```

Browser: [verify_scheduled_reports.php?run=1](http://localhost/it-management/scripts/verify_scheduled_reports.php?run=1) (no login).

## Report slugs

| Slug | Dataset |
|------|---------|
| `equipment_summary` | Equipment by type |
| `ticket_summary` | Tickets by status |
| `hr_summary` | Employees by department |
| `budget_summary` | Budget vs actual trend |
| `asset_value` | Asset financial value |

## Email formats

- **pdf** — HTML body plus `.html` attachment (print-friendly).
- **xlsx** — HTML body plus CSV attachment named `.xlsx` for Excel.

Delivery uses `itm_send_email()` with optional MIME attachments (`includes/itm_email.php`).
