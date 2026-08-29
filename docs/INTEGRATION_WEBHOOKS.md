# Integration Webhooks

Outbound HTTP webhook delivery for tenant integrations. Subscribers register HTTPS endpoints; the app enqueues signed JSON payloads and a cron worker delivers them with retries.

## Tables

| Table | Purpose |
|-------|---------|
| `integration_webhooks` | Subscriber config: `target_url`, comma-separated `event_types`, encrypted signing secret, `max_attempts`, `active` |
| `integration_webhook_deliveries` | Queue rows: `status`, `attempt_count`, `next_attempt_at`, `last_error`, `payload_json` |

Canonical DDL: `db/01_schema.sql`. Helpers: `includes/itm_webhook_queue.php`.

## Admin UI

[modules/integration_webhooks/index.php](http://localhost/it-management/modules/integration_webhooks/index.php) — Admin session. Create hooks, pick event types, generate signing secret, view delivery log.

## Event types

| Event | When enqueued |
|-------|----------------|
| `ticket.created` | After successful ticket create |
| `ticket.status_changed` | After ticket edit when `status_id` changes (`modules/tickets/create.php`) |
| `ticket.priority_changed` | After ticket edit when `priority_id` changes (`modules/tickets/create.php`) |
| `ticket.comment_created` | After ticket comment create (`includes/itm_ticket_comments.php`, `modules/ticket_comments/index.php`) |
| `ticket.survey_completed` | After ticket survey submit (`includes/itm_ticket_survey.php` → `itm_webhook_queue_enqueue()`) |
| `alert.created` | After alert create (`modules/alerts/index.php`) |
| `expense.created` | After expense create (`modules/expenses/index.php`) |
| `expense.approved` | After expense edit when `paid_status_id` transitions into Posted/Paid (`modules/expenses/index.php`) |
| `employee_onboarding.approved` | After email-link approval decision **approve** on `modules/employee_onboarding_requests/index.php?approval_api=1` |
| `equipment.disposed` | After disposal recorded via `itm_asset_lifecycle_record_disposal()` (`modules/equipment/view.php`) |
| `hotel_booking.confirmed` | Hotel booking distribution flows |

Helpers:

- `itm_webhook_queue_emit_ticket_status_changed($conn, $companyId, $ticketRow, $extra)`
- `itm_webhook_queue_emit_ticket_priority_changed($conn, $companyId, $ticketRow, $extra)`
- `itm_webhook_queue_emit_ticket_comment_created($conn, $companyId, $commentRow)`
- `itm_webhook_queue_emit_alert_created($conn, $companyId, $alertRow)`
- `itm_webhook_queue_emit_expense_created($conn, $companyId, $expenseRow)`
- `itm_webhook_queue_emit_expense_approved($conn, $companyId, $expenseRow)`
- `itm_webhook_queue_emit_employee_onboarding_approved($conn, $companyId, $requestRow, $approvalTarget)`
- `itm_webhook_queue_emit_equipment_disposed($conn, $companyId, $equipmentRow)`

Automation rules may also queue events via action `emit_webhook` (`includes/itm_automation_rules.php`).

## Delivery worker

```bash
php scripts/run_integration_webhooks.php
```

Schedule every 1–5 minutes. Validates URLs (blocks private/loopback), signs body with HMAC-SHA256 header, retries with backoff until `max_attempts`.

## Payload shape

Each delivery stores JSON including at least:

```json
{
  "event": "ticket.status_changed",
  "company_id": 1,
  "ticket_id": 42,
  "old_status_id": 1,
  "new_status_id": 2
}
```

Exact fields vary by event; subscribers should tolerate unknown keys.

## Regression

```bash
php scripts/verify_integration_webhooks.php
```

Browser: [verify_integration_webhooks.php?run=1](http://localhost/it-management/scripts/verify_integration_webhooks.php?run=1) (Admin session).

## Security

- URL validation rejects localhost and private IP ranges (SSRF guard).
- Signing secret stored encrypted (`itm_webhook_queue_encrypt_secret()`).
- No inbound webhook receiver in this module — outbound only.
