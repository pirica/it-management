# AGENT_NOTES.md - Notifications API

## 1. Module Purpose

JSON API for the global header notification bell (`includes/header.php`). Not a CRUD module — read/mark-read only.

## 2. Key Tables

- **employee_notifications** — reads and updates rows for the signed-in employee only.

## 3. Required Relationships

- Scoped by session `company_id` + `employee_id`.

## 6. API Actions

- **GET** `api.php?count_only=1` — `{ ok, unread_count, inbox_url }` (badge poll; no list query)
- **GET** `api.php?unread=0&limit=20` — `{ ok, unread_count, notifications[], inbox_url }` (responses send `Cache-Control: no-store`)
- **GET** `api.php?stream=1` — SSE (`event: unread`) — **not** auto-started by `js/notifications.js` (ties up PHP workers)
- **POST** `action=mark_read` + `notification_id` + CSRF
- **POST** `action=mark_all_read` + CSRF — `itm_employee_notification_mark_all_read()` (tenant + `active = 1` scope aligned with unread count)

## 7. File Structure

- **api.php** — JSON entry
- **index.html** — directory listing guard

## 8. Multi-Tenant Rules

- Never return another employee's rows.

## 12. Module Owner Notes

- Real-time: deferred badge poll (`?count_only=1`); full list on bell open; **no** auto SSE (worker exhaustion). **No** `itm_api_enforce_rate_limit_or_exit()` on this API.
- Footer: **Mark all read** marks every unread row; on success the same button relabels to **Exit** (closes the dropdown). Reopens as **Mark all read** when the panel opens again or new unread items arrive.
- Access: slug `notifications` is in `itm_module_access_always_allowed_slugs()` — not gated by Company Module Access (header bell must work for every signed-in user).
- Canonical doc: `docs/NOTIFICATIONS.md`.
- Regression: `php scripts/verify_employee_notifications.php`.
