# AGENT_NOTES.md - Appointment

## 1. Module Purpose

Employee self-service IT appointment scheduling: choose a **reason for your appointment**, pick an hourly slot from a weekly grid modal, and confirm **In-person** or **Remote** (when allowed). Staff with list access can review company bookings via `list_all.php` and `view.php`. Tenant configuration lives in **`modules/appointment_settings/`** (separate RBAC slug).

## 2. Key Tables

- **appointments** — booked slots (`appointment_date`, `start_time`, `end_time`, `appointment_type_id`, `assigned_to_employee_id`, `is_confirmed`, `status`, `timezone`, `booking_lock`)
- **appointment_type** — tenant lookup (`name` + `label`); core `in_person` / `remote`; additional active types when allowed per weekday
- **appointment_visit_reasons** — dropdown reasons (active rows only in booking UI)
- **appointment_settings** — one row per company: timezone, slot length, bookable window, check-in buffer (modality only on `appointment_business_hours`)
- **appointment_business_hours** — seven rows per company (`allows_in_person`, `allows_remote`, `allowed_types_json`, open/close, `is_closed`). **Company 1 seed grid** (regression): Sun/Sat closed; Mon/Tue/Thu/Fri both modalities; **Wed remote-only** — see `itm_appointment_regression_sample_business_hours_by_dow()` in `includes/itm_appointment.php` and `db/02_data.sql`.

## 3. Required Relationships

- **appointments** → `companies`, `employees`, `appointment_visit_reasons`, `appointment_type`, optional `assigned_to_employee_id` → `employees`
- Configuration tables → `companies` (CASCADE)
- Booking reads settings/hours/reasons/types via `includes/itm_appointment.php`

## 4. Business Rules (Critical for Agents)

- All queries scoped by `company_id`.
- Slot grid: weekday must be bookable (`is_closed = 0` and at least one of `allows_in_person` / `allows_remote`); slots generated between `bookable_start_time` and `bookable_end_time` using `slot_duration_minutes`; existing `scheduled` rows with same date/start block availability (`booking_lock` unique per company).
- **Appointment types:** controlled per weekday via `appointment_business_hours.allowed_types_json` (and legacy `allows_in_person` / `allows_remote` for core slugs). Booking UI (`js/appointment.js`) and `api.php` `schedule` enforce `itm_appointment_hour_allows_type_name()` after slot selection. All **active** types can appear as cards when allowed that day.
- When only one type is allowed for the selected day, UI shows a single type card and an info banner (uses type **label**).
- When multiple types are allowed, booking UI pre-selects `appointment_settings.default_appointment_modality` when that slug is allowed, otherwise the first allowed type.
- Schedule without a visit reason or slot: `js/appointment.js` alerts `--Select a reason for your appointment--` and `Select an appointment time.` (schedule button stays enabled so alerts always run).
- API accepts any **active** `appointment_type.name` allowed for the selected weekday (must resolve to `appointment_type_id`).
- Visit reasons on schedule must be `active = 1` and not soft-deleted.
- New bookings insert `status = 'scheduled'`.
- **Status workflow (list/view):** staff with RBAC **edit** may set `scheduled`, `completed`, `no_show`, or `cancelled` via inline list `<select>` (POST `appointment_status_update` on `list_all.php`) or view form (POST `appointment_status_update` on `view.php`). `completed`, `no_show`, and `cancelled` clear `booking_lock` so the slot can be rebooked. Helpers: `appt_status_options()`, `appt_status_badge()`, `appt_update_appointment_status()` in `index.php`. Schema enum includes `no_show` (`db/01_schema.sql`; migration `db/migrations/appointments_status_no_show.sql` for live DBs).
- Soft-delete on **appointments** clears `booking_lock`, sets `status = cancelled`, and stamps `deleted_*` in the delete handler (`index.php` delete POST) so the slot is bookable again.
- **`appointment_settings.active`:** when `active = 0`, `itm_appointment_build_week_slots()` returns `booking_disabled` and schedule/reschedule API actions return HTTP 403 with a clear message. Booking UI shows a banner and disables scheduling.
- **Past slots:** `itm_appointment_slot_is_past()` marks past slots unavailable in the grid; schedule/reschedule reject past times server-side.
- **Self-service cancel/reschedule:** owner (`employee_id`) or admin (`itm_appointment_employee_can_modify()` / `appt_employee_can_modify()`) on `status = scheduled` rows. View page: emoji-only **📅** Reschedule and **🗑️** Cancel. Cancel sets `cancelled`, clears `booking_lock`, notifies assignee. Reschedule: `reschedule_prepare` clears lock; modal picks new slot; `reschedule` updates row in a transaction with new `booking_lock`.
- **Confirmation email:** `itm_appointment_send_confirmation_email()` after schedule and reschedule — HTML body + `.ics` attachment via `itm_send_email()`; assignee CC when set and different from booker.
- **List filters:** `list_all.php` supports `filter=mine`, `date_from` / `date_to` (dd/mm/yyyy or `Y-m-d`), search, sort, pagination via `records_per_page`.

## 5. UI Behavior Requirements

### Booking (`index.php`, `crud_action` default)

- Copy: “What is the reason for your appointment?” / “--Select a reason for your appointment--”.
- Slot picker: modal week grid (`js/appointment.js`, `css/appointment.css`); confirm sets readonly display + hidden `appointment_date` / `start_time` / `end_time`.
- Appointment type: card-style radios for each active type; **hidden until a slot is confirmed**; visibility uses `week_slots` per-day `allowed_types` (and embedded `data-modality-config`). Type card titles use `appointment_type.label`.
- Schedule: AJAX POST to `api.php` `action=schedule` with CSRF; success redirects to `view.php?id=`; sends confirmation email + `.ics`.
- **👤 My appointments** link → `list_all.php?filter=mine`. Inactive `appointment_settings` shows banner and disables booking controls.
- Sidebar card: simplified Mon–Fri / Sat–Sun summary (see pitfalls — not a full per-day grid).
- **⚙️ Appointment Settings** link: only when `itm_is_admin($conn, $employee_id)` — not RBAC `appointment_settings` edit permission.

### List / view (`list_all.php`, `view.php`)

- List: tenant appointments with **filter=mine**, **date_from** / **date_to**, server-side **search**, **sort**, **pagination** (`itm_resolve_records_per_page()`).
- **Assignee notifications:** when `assigned_to_employee_id` changes on `list_all` inline POST, `itm_notify_appointment_assigned()` notifies the assignee (including self-assign). Row form already posts hidden `id`.
- View: detail includes status badge; owner/admin on **scheduled** rows get emoji-only **📅** Reschedule and **🗑️** Cancel (`api.php`); optional staff status form (💾), assignee, confirmed, audit meta.

### Not flattened scaffold CRUD

- Booking `index.php` (default action) has no search/pagination/bulk delete/Excel import — bespoke slot modal UI.
- Admin **`list_all.php`** uses list search/sort/pagination; still no bulk delete, Excel import, or `data-itm-db-import-endpoint`.
- Documented bespoke booking flow (`AGENTS.md` Appointment scheduling).

## 6. API Actions

`modules/appointments/api.php` — JSON, rate limit + session required.

| Action | Method | Purpose |
|--------|--------|---------|
| **week_slots** | GET `date`, optional `exclude_appointment_id` | Week grid; `booking_disabled` when settings inactive |
| **schedule** | POST + CSRF | Create appointment; confirmation email + ICS; returns `view_url` |
| **cancel** | POST + CSRF | Owner/admin cancel; clears lock; notifies assignee |
| **reschedule_prepare** | POST + CSRF | Clears `booking_lock` before slot modal |
| **reschedule** | POST + CSRF | Atomic slot swap + confirmation email + ICS |

## 7. File Structure

- **index.php** — booking UI, list, view, delete POST when `$crud_action === 'delete'`
- **list_all.php** / **view.php** / **delete.php** — wrappers setting `$crud_action`
- **api.php** — slot + schedule JSON
- **create.php** / **edit.php** — not used for booking (wrappers or absent)
- **includes/itm_appointment.php** — settings, hours, slot builder, types, reasons
- **js/appointment.js** — modal, week navigation, schedule fetch
- **css/appointment.css** — layout, modal, type cards

## 8. Multi-Tenant Rules

- `company_id` from session on all reads/writes.
- Default list shows all tenant appointments; `filter=mine` scopes to session `employee_id`.

## 9. Audit Logging Requirements

- `appointments` and configuration tables: `trg_{table}_audit_*` in `db/03_triggers.sql` (unconditional INSERT into `audit_logs` on DML).
- Schedule path uses prepared INSERT in `api.php` (triggers fire).

## 10. Common Pitfalls

- Do not assume delete is employee-scoped: delete POST only checks `company_id`, not booking owner (use self-service **cancel** API for owner/admin on scheduled rows).
- Sidebar hours text hardcodes **“(BST)”**; timezone label elsewhere uses `appointment_settings.timezone` (can disagree).
- Mon–Fri line uses **first open weekday row** only — misleading if Tuesday hours differ from Monday.
- Empty visit-reason dropdown if all reasons inactive/deleted — no empty-state message on booking form.
- Slot display after confirm uses **ISO date** in the text field (`YYYY-MM-DD`), not `dd/mm/yyyy` display helper.
- Errors use browser `alert()` — no inline flash on booking screen.
- `itm_appointment_load_settings()` returns the row regardless of `active`; use `itm_appointment_settings_booking_enabled()` before booking.

## 11. Examples of Safe Code Patterns

```php
$stmt = mysqli_prepare($conn, 'SELECT a.* FROM appointments a WHERE a.company_id = ? AND a.id = ? AND a.deleted_at IS NULL LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $company_id, $appointmentId);
```

## 12. Module Owner Notes

### Local URLs (open in a new browser tab)

- Booking: [modules/appointments/index.php](http://localhost/it-management/modules/appointments/index.php)
- List: [modules/appointments/list_all.php](http://localhost/it-management/modules/appointments/list_all.php)
- My appointments: [list_all.php?filter=mine](http://localhost/it-management/modules/appointments/list_all.php?filter=mine)
- Settings (admin): [modules/appointment_settings/index.php](http://localhost/it-management/modules/appointment_settings/index.php)

### Regression

```bash
php scripts/verify_appointment.php
php -l modules/appointments/index.php
php -l modules/appointments/api.php
```

### Remaining backlog

| Area | Gap | Suggested direction |
|------|-----|---------------------|
| UX | ISO date in slot summary | Use `itm_format_date_display()` in JS or server-side label |
| UX | Sidebar timezone label | Derive from settings, remove hardcoded BST |
| Admin link | Only `itm_is_admin` | Also show ⚙️ when role has `appointment_settings` **edit** |
| QA | No dedicated MBQA slug step | Add `module_browser_qa_runner.php --module=appointment` when flow stabilizes |

### Migration (existing DBs)

- `db/migrations/appointments.sql` — full module tables
- `db/migrations/appointment_booking_lock.sql` — `booking_lock` + unique index
- `db/migrations/appointment_type.sql` — enum → `appointment_type_id` (destructive)

Fresh installs: `db/01_schema.sql`, `db/02_data.sql`, `db/03_triggers.sql` only.
