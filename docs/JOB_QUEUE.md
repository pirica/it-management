# Job Queue

Generic background job queue for long-running or retriable work outside Apache request cycles.

## Overview

| Piece | Location |
|-------|----------|
| Table | `job_queue` in `db/01_schema.sql` |
| Helper | `includes/itm_job_queue.php` |
| Worker | `scripts/run_job_queue.php` (cron every minute) |
| Admin UI | `modules/job_queue/` (Admin read-only + manual retry) |
| Regression | `php scripts/verify_job_queue.php` |

## Enqueue

```php
itm_job_queue_enqueue($conn, $companyId, 'email_send', [
    'to' => 'user@example.com',
    'subject' => 'Subject',
    'body' => '<p>HTML body</p>',
], $priority = 5, $maxAttempts = 5, $scheduledAt = null, $createdBy = 0);
```

`company_id` may be `null` for global jobs (FK allows NULL).

## Job types and payloads

| `job_type` | Payload keys | Handler |
|------------|--------------|---------|
| `webhook_delivery` | `delivery_id` | Delivers one `integration_webhook_deliveries` row via `itm_webhook_queue_deliver_row()` |
| `scheduled_report` | `scheduled_report_id` | Sends one `scheduled_reports` row via `itm_scheduled_reports_send_row(..., true)` |
| `network_discovery` | `profile_id`, optional `employee_id` | Runs discovery scan batches via `background_jobs` bridge until complete |
| `license_compliance` | (none required) | Scans `license_management` vs `software_license_links` seat counts per company |
| `email_send` | `to`, `subject`, `body`, optional `options` | `itm_send_email()` |

## Worker locking

- Process-level: `GET_LOCK('itm_job_queue_worker', 0)` — only one worker at a time.
- Row-level: `FOR UPDATE SKIP LOCKED` inside a transaction when claiming pending rows (fallback: optimistic `UPDATE`).

## Retries

- Automatic: `itm_job_queue_mark_failed()` increments `attempts`, applies linear backoff (`60 * attempts` seconds, cap 3600), requeues as `pending` until `max_attempts`.
- Manual: Admin **Retry** on [modules/job_queue/index.php](http://localhost/it-management/modules/job_queue/index.php) calls `itm_job_queue_retry_failed()`.

## Phase 2 (not in initial deliverable)

Optionally route `integration_webhook_deliveries` dispatch through `job_queue` instead of `run_webhook_queue.php` alone. Phase 1 keeps dedicated runners parallel.

## Cron example

```bash
* * * * * php /path/to/it-management/scripts/run_job_queue.php --limit=20 >> /var/log/itm-job-queue.log 2>&1
```
