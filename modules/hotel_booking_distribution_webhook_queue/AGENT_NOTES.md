# AGENT_NOTES.md - Distribution Webhook Queue

## 1. Module Purpose

**Outbound webhook delivery queue** for distribution channels: pending ARI/reservation payloads POSTed to channel `webhook_url`, with retry scheduling (`status`, `attempt_count`, `max_attempts`, `next_retry_at`, `last_http_code`, `last_error`).

Populated by `itm_hotel_booking_distribution_push_ari_to_webhook()` and related helpers; processed by [run_hotel_booking_distribution_webhook_queue.php?run=1](http://localhost/it-management/scripts/run_hotel_booking_distribution_webhook_queue.php?run=1). Channel **view** shows dead/failed rows for ops.

## 2. Key Tables

- **hotel_booking_distribution_webhook_queue** — `direction` (default `outbound`), `event_type`, `target_url`, `payload_body`, delivery status fields

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- `channel_id` → `hotel_booking_distribution_channels` (`ON DELETE CASCADE`)
- Optional `hotel_id` (no FK in schema — nullable int for context)

## 4. Business Rules (Critical for Agents)

- `status`: `pending`, `failed`, `delivered`, `dead` (verify in distribution webhook runner).
- `max_attempts` on queue row may mirror channel `webhook_max_attempts` at enqueue time.
- Do not hard-delete rows during active retry windows without checking ops scripts.

## 5. UI Behavior Requirements

Flattened scaffold CRUD for inspection; large `payload_body` — use `view.php` for full content. Standard scaffold list contract otherwise.

## 9. Audit Logging Requirements

- `trg_hotel_booking_distribution_webhook_queue_audit_*` in `db/03_triggers.sql`.

## 12. Module Owner Notes

- Ops report: [report_hotel_booking_distribution_webhook_ops.php?run=1](http://localhost/it-management/scripts/report_hotel_booking_distribution_webhook_ops.php?run=1)
- Channel view webhook table links here for failed/dead items.
