# AGENT_NOTES.md — modules/job_queue/

## 1. Module Purpose

Admin read-only monitor for the generic `job_queue` table. Operators filter by job type and status, inspect payload JSON, and manually retry failed or done jobs. Jobs are enqueued via `itm_job_queue_enqueue()` in `includes/itm_job_queue.php` and processed by `scripts/run_job_queue.php`.

## 2. Tables

| Table | Role |
|-------|------|
| `job_queue` | Tenant-scoped (nullable `company_id`) work items: `job_type`, `payload_json`, `status`, `priority`, `attempts`, `max_attempts`, `scheduled_at`, timestamps, `last_error`. |

## 4. Business Rules

- **Admin only:** `itm_is_admin()` on every entry file; module is RBAC-exempt (`job_queue` in `itm_crud_rbac_exempt_module_slugs()`).
- **No create/edit/delete CRUD:** wrappers redirect to `index.php`; rows are created by application code or workers only.
- **Manual retry:** POST `retry_job_id` on `index.php` (CSRF) calls `itm_job_queue_retry_failed()` — resets `failed`/`done` rows to `pending`.
- **Handler types:** `webhook_delivery`, `scheduled_report`, `network_discovery`, `license_compliance`, `email_send` (see `docs/JOB_QUEUE.md`).
- **Parallel runners (phase 1):** `integration_webhook_deliveries` and `background_jobs` keep their dedicated cron scripts; optional migration into `job_queue` is phase 2.

## 7. File Structure

| File | Role |
|------|------|
| `index.php` | List, filters, KPI counts, retry POST |
| `view.php` | Read-only detail + payload JSON |
| `create.php` / `edit.php` / `delete.php` / `list_all.php` | Redirect to index |

## 12. Module Owner Notes

- UI: [job_queue/index.php](http://localhost/it-management/modules/job_queue/index.php) (Admin session)
- Worker: [run_job_queue.php?run=1](http://localhost/it-management/scripts/run_job_queue.php?run=1) (Admin session)
- Regression: `php scripts/verify_job_queue.php`
