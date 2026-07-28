# AGENT_NOTES.md - Appointment Settings

## 1. Module Purpose

Tenant admin UI for `modules/appointment/`: edit `appointment_settings`, weekly `appointment_business_hours`, manage `appointment_visit_reasons`, and view fixed `appointment_type` lookup rows (`in_person`, `remote`).

## 2. Key Tables

- **appointment_settings** — one row per company (timezone, in-person-only flag, slot length, bookable window, check-in buffer)
- **appointment_business_hours** — seven rows per company (`allows_online_booking`, open/close times)
- **appointment_visit_reasons** — booking dropdown reasons (soft-delete from this module)
- **appointment_type** — seeded per company; read-only in UI (names drive booking modality)

## 3. Required Relationships

- All tables → `companies` (CASCADE)
- Booking module reads the same rows via `includes/itm_appointment.php`

## 4. Business Rules (Critical for Agents)

- `itm_appointment_settings_ensure_company_config()` runs on each request: creates missing settings, hours (default Wed–Fri online), and type rows without overwriting existing data.
- Do not hard-delete configuration rows; visit reasons use soft-delete via `itm_crud_build_soft_delete_sql()`.
- RBAC slug: `appointment_settings` (separate from employee booking slug `appointment`).

## 5. UI Behavior Requirements

- **index.php** — hub with four tables (settings, business hours, visit reasons, appointment types); each row has **🔎 View**, **✏️ Edit**, **🗑️ Delete** (core types `in_person` / `remote` cannot be deleted).
- **create.php** — `?kind=visit_reason` or `?kind=business_hour` (Add **➕** on index).
- **edit.php** / **view.php** — `?kind=` + `id` for each entity.
- **delete.php** — POST `kind` + `id`, soft-delete.
- **aps_init.php** — shared bootstrap and page shell.

## 6. File Structure

- `index.php` — all POST handlers and markup
- `includes/itm_appointment_settings_admin.php` — ensure defaults + admin visit-reason list helper

## 7. Integration

- Sidebar: Planning → Appointment Settings (`includes/ui_config.php`)
- `modules_registry` slug: `appointment_settings`
- Admins get ⚙️ link from `modules/appointment/` list and booking toolbar (`itm_is_admin()`).

## 8. Regression

```bash
php scripts/verify_appointment.php
php -l modules/appointment_settings/index.php
php -l includes/itm_appointment_settings_admin.php
```

## 12. Module Owner Notes

Local URL: [modules/appointment_settings/index.php](http://localhost/it-management/modules/appointment_settings/index.php) — open in a new browser tab (Admin session).
