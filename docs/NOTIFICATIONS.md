# In-app notification center

Per-employee workflow notifications without opening every module.

## Architecture

| Layer | Path |
|-------|------|
| Table | `employee_notifications` (`db/01_schema.sql`) |
| Helpers | `includes/itm_employee_notifications.php` — `itm_notify_employee()`, unread count, list, mark read, digest |
| Header UI | `includes/header.php` + `css/notifications.css` + `js/notifications.js` |
| JSON API | `modules/notifications/api.php` |
| Inbox CRUD | `modules/employee_notifications/` (session-scoped list) |

## Privacy

Rows store **metadata only** (title, short body, module slug, record id, link). Do not copy vault ciphertext, email bodies, or note content into `body`.

## Emitters

| Event | Source |
|-------|--------|
| Ticket assigned | `modules/tickets/create.php` |
| Onboarding approval | `modules/employee_onboarding_requests/index.php` (after approval email) |
| Warranty expiring | `scripts/run_email_alert_rules.php` (equipment assignee) |
| Todo assigned | `modules/todo/index.php` |
| Event assigned | `modules/events/index.php` |
| Email to/cc match | `includes/itm_email.php` → `itm_email_log_send()` on successful send |
| Note shared | `modules/notes/index.php` |
| Ticket `@mention` | `modules/ticket_comments/index.php` |
| Live chat message | `modules/live_chat/api.php` |

## Operations

```bash
php scripts/verify_employee_notifications.php
php scripts/run_notification_digest.php
```

Schedule digest daily alongside `run_email_alert_rules.php`.

## Browser checks (Admin session)

- Header bell: any signed-in module page
- [Notification API](http://localhost/it-management/modules/notifications/api.php?unread=0)
- [Employee Notifications inbox](http://localhost/it-management/modules/employee_notifications/index.php)
