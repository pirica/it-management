# AGENT_NOTES.md - Ticket Activity

## 1. Module Purpose

Append-only **system event** log for support tickets (`ticket_activity`). Rows are written by `itm_ticket_activity_log()` from ticket edits, comments, SLA, surveys, live chat, problem management, and merge flows. The primary user-facing timeline is the **Activity** card on [modules/tickets/view.php](http://localhost/it-management/modules/tickets/view.php), which merges `ticket_comments` with non-duplicate `ticket_activity` events via `itm_ticket_unified_activity_feed()`.

## 2. Key Tables

- **ticket_activity** — per-ticket timeline events (`event_type`, `payload_json`, optional `actor_employee_id`).
- **tickets** — parent FK (`ticket_id`).

## 3. Required Relationships

- **ticket_activity.company_id** → **companies**
- **ticket_activity.ticket_id** → **tickets**
- **ticket_activity.actor_employee_id** → **employees** (nullable for system/cron events)

## 4. Business Rules (Critical for Agents)

- **Append-only UX:** prefer `itm_ticket_activity_log()` from mutation paths; do not ask agents to hand-insert rows except via this helper or admin CRUD.
- **Unified feed:** `itm_ticket_unified_activity_feed()` shows comment bodies from `ticket_comments` and skips `comment_added` activity rows (avoids duplicates).
- **Core ticket edits:** `itm_ticket_log_edit_field_changes()` in `includes/itm_ticket_activity.php` logs `status_changed`, `priority_changed`, and `assigned` from [modules/tickets/create.php](modules/tickets/create.php) on edit.
- **Archive:** [modules/tickets/archive.php](modules/tickets/archive.php) logs `archived` / `unarchived`.
- **Not private-data exempt** — audit triggers required.

## 5. UI Behavior Requirements

- Flattened admin CRUD under this folder (list/view for operators); hide **`company_id`** from list/view/forms.
- Ticket view Activity card: chronological comments + system events; comment form posts `add_ticket_comment` with CSRF.

## 6. API Actions (If Applicable)

- N/A — events are written from shared helpers and ticket handlers, not a public JSON API.

## 7. File Structure

- **index.php** — flattened list/view/admin CRUD for `ticket_activity`
- **create.php**, **edit.php**, **delete.php**, **view.php**, **list_all.php** — standard wrappers

## 8. Multi-Tenant Rules

- All queries scoped by session **`company_id`**; feed helpers require matching `company_id` + `ticket_id`.

## 9. Audit Logging Requirements

- **`trg_ticket_activity_audit_insert|update|delete`** in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Logging `comment_added` in the feed **and** rendering the comment row — use unified feed helper only.
- Forgetting activity hooks when adding new ticket mutation paths (edit, archive, merge, inbound email).
- Showing raw `event_type` or JSON to end users — use `itm_ticket_activity_format_event_summary()`.

## 11. Examples of Safe Code Patterns

### Log a status change

```php
itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, 'status_changed', [
    'from_status_id' => $prevStatusId,
    'to_status_id' => $newStatusId,
    'from_status_name' => $fromName,
    'to_status_name' => $toName,
]);
```

### Load unified Activity feed

```php
$feed = itm_ticket_unified_activity_feed($conn, $companyId, $ticketId, $viewerEmployeeId, $isSupportAgent);
```

## 12. Module Owner Notes (Optional)

- Regression: `php scripts/verify_ticket_activity.php` — [verify_ticket_activity.php?run=1](http://localhost/it-management/scripts/verify_ticket_activity.php?run=1) (Admin session for browser landing).
- Related: `includes/itm_ticket_comments.php`, `modules/ticket_comments/AGENT_NOTES.md`, `modules/tickets/AGENT_NOTES.md`.
