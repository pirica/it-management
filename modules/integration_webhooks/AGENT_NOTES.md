# AGENT_NOTES.md - Integration Webhooks

## 1. Module Purpose

Tenant-scoped outbound webhook endpoints for integration partners. Subscribes to event types and delivers JSON payloads with HMAC signing and retries.

## 2. Key Tables

- **integration_webhooks** — endpoint config (`name`, `target_url`, `event_types` CSV, encrypted `secret_encrypted`, `max_attempts`).
- **integration_webhook_deliveries** — queue/history (`status` pending|delivered|failed|dead, retry schedule).

## 3. Business Rules

- **Secrets:** Generated on create; hidden from list/view/forms. Rotate on edit via **Rotate signing secret** checkbox. Encryption: `includes/itm_webhook_queue.php`.
- **URL hardening:** SSRF checks reuse hotel distribution webhook URL validator (blocks private/metadata hosts).
- **Event catalog:** `ticket.created`, `ticket.status_changed`, `alert.created`, `expense.created`, `employee_onboarding.approved`, `equipment.disposed`, `hotel_booking.confirmed` (`itm_webhook_queue_event_types()`).
- **Emitters:** `modules/tickets/create.php`, `modules/alerts/index.php`, `modules/expenses/index.php` (create), `modules/employee_onboarding_requests/index.php` (`approval_api` approve), `itm_asset_lifecycle_record_disposal()`, hotel booking flows.
- **Processor:** `php scripts/run_integration_webhooks.php` — [browser](http://localhost/it-management/scripts/run_integration_webhooks.php?run=1).
- **Verify:** `php scripts/verify_integration_webhooks.php`.

## 4. UI

Flattened CRUD scaffold: [modules/integration_webhooks/index.php](http://localhost/it-management/modules/integration_webhooks/index.php) — **Admin session** required.

## 5. Related docs

- `docs/INTEGRATION_WEBHOOKS.md`
