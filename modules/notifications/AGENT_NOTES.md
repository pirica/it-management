# AGENT_NOTES.md - Notifications API

## 1. Module Purpose

JSON API for the global header notification bell (`includes/header.php`). Not a CRUD module — read/mark-read only.

## 2. Key Tables

- **employee_notifications** — reads and updates rows for the signed-in employee only.

## 3. Required Relationships

- Scoped by session `company_id` + `employee_id`.

## 6. API Actions

- **GET** `api.php?unread=0&limit=20` — `{ ok, unread_count, notifications[], inbox_url }`
- **POST** `action=mark_read` + `notification_id` + CSRF
- **POST** `action=mark_all_read` + CSRF

## 7. File Structure

- **api.php** — JSON entry
- **index.html** — directory listing guard

## 8. Multi-Tenant Rules

- Never return another employee's rows.

## 12. Module Owner Notes

- Poll interval: 60s (`js/notifications.js`).
- Canonical doc: `docs/NOTIFICATIONS.md`.
- Regression: `php scripts/verify_employee_notifications.php`.
