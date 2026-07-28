# AGENT_NOTES.md - Appointment

## 1. Module Purpose

Employee self-service IT appointment scheduling: choose a **reason for your appointment**, pick an hourly slot from a weekly grid modal, and confirm **In-person** or **Remote** (when allowed). Staff with list access can review company bookings via `list_all.php` and `view.php`. Tenant configuration lives in **`modules/appointment_settings/`** (separate RBAC slug).

## 2. Key Tables

- **appointments** — booked slots (`appointment_date`, `start_time`, `end_time`, `appointment_type_id`, `status`, `timezone`, `booking_lock`)
- **appointment_type** — tenant lookup (`in_person`, `remote`) for modality
- **appointment_visit_reasons** — dropdown reasons (active rows only in booking UI)
- **appointment_settings** — one row per company: timezone, `allow_in_person` / `allow_remote`, mirrored `in_person_only`, slot length, bookable window, check-in buffer
- **appointment_business_hours** — seven rows per company (`allows_in_person`, `allows_remote`, open/close, `is_closed`). **Company 1 seed grid** (regression): Sun/Sat closed; Mon/Tue/Thu/Fri both modalities; **Wed remote-only** — see `itm_appointment_regression_sample_business_hours_by_dow()` in `includes/itm_appointment.php` and `db/02_data.sql`.

## 3. Required Relationships

- **appointments** → `companies`, `employees`, `appointment_visit_reasons`, `appointment_type`
- Configuration tables → `companies` (CASCADE)
- Booking reads settings/hours/reasons/types via `includes/itm_appointment.php`

## 4. Business Rules (Critical for Agents)

- All queries scoped by `company_id`.
- Slot grid: weekday must be bookable (`is_closed = 0` and at least one of `allows_in_person` / `allows_remote`); slots generated between `bookable_start_time` and `bookable_end_time` using `slot_duration_minutes`; existing `scheduled` rows with same date/start block availability (`booking_lock` unique per company).
- **Appointment type (In-person / Remote):** company `appointment_settings.allow_in_person` / `allow_remote` gate types globally; **per weekday** `appointment_business_hours.allows_in_person` / `allows_remote` further restrict types after the employee picks a slot date. Booking UI (`js/appointment.js`) and `api.php` `schedule` both enforce the intersection (`itm_appointment_day_allows_modality()` / `itm_appointment_modality_for_date()`).
- When only one modality is allowed (company-wide or for the selected day), UI shows a single type card and an info banner.
- API accepts only `appointment_type` names `in_person` and `remote` (must be active lookup rows).
- Visit reasons on schedule must be `active = 1` and not soft-deleted.
- New bookings insert `status = 'scheduled'`; no status workflow UI yet.
- Soft-delete on **appointments** clears `booking_lock` in the delete handler (`index.php` delete POST).
- **`appointment_settings.active` is not checked** before booking — inactive settings still load and allow scheduling until code gates on `active = 1` (known gap).

## 5. UI Behavior Requirements

### Booking (`index.php`, `crud_action` default)

- Copy: “What is the reason for your appointment?” / “--Select a reason for your appointment--”.
- Slot picker: modal week grid (`js/appointment.js`, `css/appointment.css`); confirm sets readonly display + hidden `appointment_date` / `start_time` / `end_time`.
- Appointment type: card-style radios; **hidden until a slot is confirmed**; visibility uses `week_slots` per-day `allows_in_person` / `allows_remote` (same rules as the API). Both `in_person` and `remote` lookup rows should be **active** in Appointment Settings types.
- Schedule: AJAX POST to `api.php` `action=schedule` with CSRF; success redirects to `view.php?id=`.
- Sidebar card: simplified Mon–Fri / Sat–Sun summary (see pitfalls — not a full per-day grid).
- **⚙️ Appointment Settings** link: only when `itm_is_admin($conn, $employee_id)` — not RBAC `appointment_settings` edit permission.

### List / view (`list_all.php`, `view.php`)

- List: up to **200** rows, all company appointments (no “mine only” filter), columns Date/Time/Employee/Reason/Type/Status; actions **🔎 View** only.
- View: detail + audit meta via `itm_crud_render_audit_cell_value()` when available.
- **No cancel/delete button** on list or view despite `delete.php` → soft-delete POST on `index.php` (handler exists, UI missing).

### Not flattened scaffold CRUD

- No search, pagination, bulk delete, Excel import/export, or `data-itm-db-import-endpoint`.
- Bespoke by design (`AGENTS.md` Appointment scheduling).

## 6. API Actions

`modules/appointment/api.php` — JSON, rate limit + session required.

| Action | Method | Purpose |
|--------|--------|---------|
| **week_slots** | GET `date=YYYY-MM-DD` | Week grid payload (`itm_appointment_build_week_slots`) |
| **schedule** | POST + CSRF | Create appointment; returns `view_url` on success |

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
- List/view show **any** employee’s appointment in the tenant — not restricted to `employee_id = session` (IT desk model; document when adding “my appointments” filter).

## 9. Audit Logging Requirements

- `appointments` and configuration tables: `trg_{table}_audit_*` in `db/03_triggers.sql` (unconditional INSERT into `audit_logs` on DML).
- Schedule path uses prepared INSERT in `api.php` (triggers fire).

## 10. Common Pitfalls

- Do not assume delete is employee-scoped: delete POST only checks `company_id`, not booking owner.
- Past dates/times still appear in the slot grid — no “future only” filter in `itm_appointment_build_week_slots()`.
- Sidebar hours text hardcodes **“(BST)”**; timezone label elsewhere uses `appointment_settings.timezone` (can disagree).
- Mon–Fri line uses **first open weekday row** only — misleading if Tuesday hours differ from Monday.
- Empty visit-reason dropdown if all reasons inactive/deleted — no empty-state message on booking form.
- Slot display after confirm uses **ISO date** in the text field (`YYYY-MM-DD`), not `dd/mm/yyyy` display helper.
- Errors use browser `alert()` — no inline flash on booking screen.
- `itm_appointment_load_settings()` does not filter `active = 1`.

## 11. Examples of Safe Code Patterns

```php
$stmt = mysqli_prepare($conn, 'SELECT a.* FROM appointments a WHERE a.company_id = ? AND a.id = ? AND a.deleted_at IS NULL LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $company_id, $appointmentId);
```

## 12. Module Owner Notes

### Local URLs (open in a new browser tab)

- Booking: [modules/appointment/index.php](http://localhost/it-management/modules/appointment/index.php)
- List: [modules/appointment/list_all.php](http://localhost/it-management/modules/appointment/list_all.php)
- Settings (admin): [modules/appointment_settings/index.php](http://localhost/it-management/modules/appointment_settings/index.php)

### Regression

```bash
php scripts/verify_appointment.php
php -l modules/appointment/index.php
php -l modules/appointment/api.php
```

### Daily-use improvement backlog (not implemented)

| Area | Gap | Suggested direction |
|------|-----|---------------------|
| Self-service | No **cancel** / reschedule | View action 🗑️ or “Cancel” for owner (or admin); optional status `cancelled` |
| List | All-company list for every viewer | Filter **My appointments** vs **All** (admin/IT); search by employee/date |
| List | Hard cap 200 rows | Pagination or date-range filter |
| Booking | Past slots bookable in UI | Hide slots before “now” in slot builder or API validation |
| Booking | No confirmation email / calendar | `itm_send_email()` + optional ICS after schedule |
| Booking | `settings.active = 0` | Block booking with clear message |
| UX | ISO date in slot summary | Use `itm_format_date_display()` in JS or server-side label |
| UX | Sidebar timezone label | Derive from settings, remove hardcoded BST |
| Admin link | Only `itm_is_admin` | Also show ⚙️ when role has `appointment_settings` **edit** |
| Status | Raw `scheduled` string | Badges + workflow (completed, no-show, cancelled) |
| QA | No dedicated MBQA slug step | Add `module_browser_qa_runner.php --module=appointment` when flow stabilizes |

### Migration (existing DBs)

- `db/migrations/appointment.sql` — full module tables
- `db/migrations/appointment_booking_lock.sql` — `booking_lock` + unique index
- `db/migrations/appointment_type.sql` — enum → `appointment_type_id` (destructive)

Fresh installs: `db/01_schema.sql`, `db/02_data.sql`, `db/03_triggers.sql` only.
