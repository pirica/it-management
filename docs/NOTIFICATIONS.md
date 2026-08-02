# In-app notification center

Per-employee workflow notifications without opening every module.

## Architecture

| Layer | Path |
|-------|------|
| Table | `employee_notifications` (`db/01_schema.sql`) |
| Helpers | `includes/itm_employee_notifications.php` — `itm_notify_employee()`, unread count, list, mark read, digest, SSE stream |
| Header UI | `includes/header.php` + `css/notifications.css` + `js/notifications.js` |
| JSON API | `modules/notifications/api.php` |
| Inbox CRUD | `modules/employee_notifications/` (session-scoped list) |

## Privacy

Rows store **metadata only** (title, short body, module slug, record id, link). Do not copy vault ciphertext, email bodies, or note content into `body`.

## Real-time updates

- **SSE:** `GET modules/notifications/api.php?stream=1` — short-lived `text/event-stream` pushing `unread_count` (`event: unread`). Requires an **Admin session** in the browser; slug `notifications` is always allowed (not company-gated).
- **Session lock:** `modules/notifications/api.php` calls `itm_release_session_lock()` after auth (and CSRF on POST) so the ~55s SSE connection does not block other tabs (for example the inbox list).
- **Fallback:** `js/notifications.js` falls back to 60s JSON polling when `EventSource` is unavailable or the stream errors.
- **Full list:** opening the bell dropdown still uses `GET ?unread=0&limit=20`.

## Emitters

| Event | Source |
|-------|--------|
| Ticket assigned | `modules/tickets/create.php` |
| Onboarding approval | `modules/employee_onboarding_requests/index.php` (after approval email) |
| Warranty expiring | `scripts/run_email_alert_rules.php` (equipment assignee) |
| Todo assigned | `modules/todo/index.php` |
| Event assigned | `modules/events/index.php` |
| Alert assigned | `modules/alerts/index.php` (`assigned_to_employee_id` create/edit) |
| Email to/cc match | `includes/itm_email.php` → `itm_email_log_send()` on successful send |
| Note shared | `modules/notes/index.php` |
| Ticket `@mention` | `modules/ticket_comments/index.php` (save) + **F2** picker (`js/ticket-comment-mentions.js`) |
| Live chat conversation assigned | `modules/live_chat_conversations/index.php` (`assigned_to_employee_id` create/edit) |
| Live chat message / waiting | `modules/live_chat/api.php` via `itm_employee_notification_create()` |

## Operations

```bash
php scripts/verify_employee_notifications.php
php scripts/run_notification_digest.php
```

Schedule digest daily alongside `run_email_alert_rules.php`.

## Browser checks (Admin session)

- Header bell: any signed-in module page
- [Notification API](http://localhost/it-management/modules/notifications/api.php?unread=0)
- [Notification SSE stream](http://localhost/it-management/modules/notifications/api.php?stream=1)
- [Employee Notifications inbox](http://localhost/it-management/modules/employee_notifications/index.php)
- [Ticket comments create](http://localhost/it-management/modules/ticket_comments/create.php) — **F2** in body field opens mention picker
