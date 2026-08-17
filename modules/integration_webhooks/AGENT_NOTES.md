# AGENT_NOTES.md - Integration Webhooks

## 1. Module Purpose

Tenant-scoped outbound webhook endpoints for integration partners. Subscribes to event types (`ticket.created`, `hotel_booking.confirmed`) and delivers JSON payloads with HMAC signing and retries.

## 2. Key Tables

- **integration_webhooks** — endpoint config (`name`, `target_url`, `event_types` CSV, encrypted `secret_encrypted`, `max_attempts`).
- **integration_webhook_deliveries** — queue/history (`status` pending|delivered|failed|dead, retry schedule).

## 3. Business Rules

- **Secrets:** Generated on create; hidden from list/view/forms. Rotate on edit via **Rotate signing secret** checkbox. Encryption: `includes/itm_webhook_queue.php`.
- **URL hardening:** SSRF checks reuse hotel distribution webhook URL validator (blocks private/metadata hosts).
- **Emitters:** `modules/tickets/create.php` (new tickets only), `booking/rooms/room-single.php` (confirmed booking).
- **Processor:** `php scripts/run_webhook_queue.php` — [browser](http://localhost/it-management/scripts/run_webhook_queue.php?run=1).
- **Verify:** `php scripts/verify_integration_webhooks.php`.

## 4. UI

Flattened CRUD scaffold: [modules/integration_webhooks/index.php](http://localhost/it-management/modules/integration_webhooks/index.php) — **Admin session** required.
