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

- **Badge (default):** `js/notifications.js` defers the first fetch **2s** after page load, then polls `GET ?count_only=1` every **120s** (panel closed) or **60s** (panel open). Skips polls while the tab is hidden (`document.hidden`). No server-side unread query in `includes/header.php` — badge is filled by JS.
- **Full list:** opening the bell uses `GET ?unread=0&limit=20` (API sends `Cache-Control: no-store`; `fetch` uses `cache: 'no-store'`).
- **SSE (optional / legacy):** `GET ?stream=1` remains for manual use but is **not** auto-started — each tab held an Apache worker ~55s and slowed every save.
- **Session lock:** `modules/notifications/api.php` calls `itm_release_session_lock()` after auth (and CSRF on POST).
- **No API rate limit:** internal session UI only — do not call `itm_api_enforce_rate_limit_or_exit()` (it updated `ui_configuration` on every badge poll).

## Emitters

| Event | Source |
|-------|--------|
| Ticket assigned | `modules/tickets/create.php` |
| Onboarding approval | `modules/employee_onboarding_requests/index.php` (after approval email) |
| Warranty expiring | `scripts/run_email_alert_rules.php` (equipment assignee) |
| Todo assigned | `modules/todo/index.php` (create + newly added assignees on edit) |
| Event assigned | `modules/events/index.php` (create + assignee change on edit) |
| Alert assigned | `modules/alerts/index.php` (`assigned_to_employee_id` create/edit; notifies on self-assign) |
| Email to/cc match | `includes/itm_email.php` → `itm_email_log_send()` on successful send |
| Note shared | `modules/notes/index.php` (create + newly added share targets on edit) |
| Ticket `@mention` | `modules/ticket_comments/index.php` (create + new mentions on edit) + **F2** picker (`js/ticket-comment-mentions.js`) |
| Live chat conversation assigned | `modules/live_chat_conversations/index.php` (`assigned_to_employee_id` create/edit; notifies on self-assign) |
| Appointment assigned | `modules/appointments/index.php` (`list_all` inline assignee change) |
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
