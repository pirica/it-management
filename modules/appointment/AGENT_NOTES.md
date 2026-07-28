# AGENT_NOTES.md - Appointment

## 1. Module Purpose

Employee self-service IT appointment scheduling: pick a visit reason, choose an hourly slot from a weekly grid modal, and confirm in-person or remote (when allowed). Administrators can review bookings via `list_all.php` and `view.php`.

## 2. Key Tables

- **appointments** — booked slots per employee (`appointment_date`, `start_time`, `end_time`, `appointment_type`, `status`, `timezone`)
- **appointment_visit_reasons** — tenant lookup for the “reason for visiting” dropdown
- **appointment_settings** — one row per company: `timezone`, `in_person_only`, bookable window, slot duration, check-in buffer
- **appointment_business_hours** — seven rows per company for sidebar hours and which weekdays allow online booking (`allows_online_booking`)

## 3. Required Relationships

- **appointments** → `companies`, `employees`, `appointment_visit_reasons`
- All child tables → `companies` (CASCADE)

## 4. Business Rules (Critical for Agents)

- All queries scoped by `company_id`.
- Slot grid: only days with `allows_online_booking = 1` and `is_closed = 0`; hourly slots between `bookable_start_time` and `bookable_end_time` from settings; booked `scheduled` rows block the slot.
- When `appointment_settings.in_person_only = 1`, API rejects `remote` and UI hides remote radio (banner matches screenshot).
- Default seed: Mon–Tue display hours but no online slots; Wed–Fri bookable 09:00–14:00; Sat–Sun closed.
- Soft-delete on **appointments** and lookup tables via standard audit columns; mutations audited in `audit_logs` (triggers in `db/03_triggers.sql`).

## 5. UI Behavior Requirements

- **index.php** — booking form + modal week grid (`css/appointment.css`, `js/appointment.js`).
- **api.php** — `week_slots` (GET), `schedule` (POST, CSRF); rate limit enforced.
- Live Chat **Live Agent** launch menu links here instead of Knowledge Base (`includes/itm_live_chat_launch_options.php`).

## 6. File Structure

- `index.php` — booking, list, view, delete POST
- `api.php` — JSON slot/schedule API
- `includes/itm_appointment.php` — settings, hours, slot builder

## 7. Integration

- Sidebar: Planning → Appointment (`includes/ui_config.php`)
- `modules_registry` slug: `appointment`

## 8. Regression

```bash
php scripts/verify_appointment.php
php -l modules/appointment/index.php
php -l modules/appointment/api.php
php scripts/check_audit_logs_coverage.php
```

## 9. Migration

`db/migrations/appointment.sql` — DROP + CREATE all four tables; apply before importing updated `03_triggers.sql` on live DBs.

## 12. Module Owner Notes

Module browser URL (local Laragon): `http://localhost/it-management/modules/appointment/index.php`
