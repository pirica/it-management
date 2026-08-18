# AGENT_NOTES.md - Appointment Settings

## 1. Module Purpose

Tenant **administration** for `modules/appointments/`: maintain `appointment_settings`, seven `appointment_business_hours` rows, `appointment_visit_reasons`, and `appointment_type` lookup rows (`in_person`, `remote`). Employees book via the Appointment module; this module does not show the slot grid.

## 2. Key Tables

- **appointment_settings** — one row per company: timezone, slot length, bookable window, check-in buffer, `default_appointment_modality` (`remote` default when both types allowed), `active`); **no** company-level In Person / Remote columns (modality on `appointment_business_hours` only)
- **appointment_business_hours** — one row per `day_of_week` (0–6): `allows_in_person`, `allows_remote`, `allowed_types_json` (per-type flags keyed by `appointment_type.name`), open/close, `is_closed`
- **appointment_visit_reasons** — booking dropdown labels (`sort_order`, `active`)
- **appointment_type** — `name` (slug), `label` (UI), `active`; core `in_person` / `remote` used by booking defaults; custom types get columns on business-hours hub and booking when enabled per day

## 3. Required Relationships

- All tables → `companies` (CASCADE)
- **appointments** FK to visit reasons and types — soft-delete reasons/types still referenced by old rows; avoid hard delete
- Booking module consumes the same rows through `includes/itm_appointment.php` and `itm_appointment_settings_ensure_company_config()`

## 4. Business Rules (Critical for Agents)

- On every `aps_init.php` load, `itm_appointment_settings_ensure_company_config()` inserts missing settings (In Person off / Remote on), missing weekday rows (Wed–Fri **Remote** bookable by default), and missing `in_person`/`remote` types — **does not overwrite** existing configuration.
- Mutations use soft-delete (`itm_crud_build_soft_delete_sql`) except **non-core** `appointment_type` rows, which are **hard-deleted** (`DELETE`) when no live `appointments` reference the type; core `in_person` / `remote` cannot be deleted.
- **Core types** `in_person` and `remote` cannot be deleted from UI (POST blocked in `delete.php`).
- **Custom appointment types** are removed with a hard `DELETE` when no active appointments reference them; otherwise delete is rejected with a flash message.
- Deleting the sole **settings** row is allowed from UI but breaks booking until ensure runs again on next settings page hit (avoid in production).
- Deleting a **business hour** row can leave fewer than seven days — booking grid simply lacks that weekday until re-added via **➕**.
- RBAC slug **`appointment_settings`** is separate from **`appointment`** — grant IT staff settings access without full appointment delete if matrix allows.
- Changing `appointment_type.active = 0` removes type from booking UI/API (`active = 1` required in resolvers).

## 5. UI Behavior Requirements

### Hub (`index.php`)

Four read-only tables with standard actions:

| Section | Add ➕ | View 🔎 | Edit ✏️ | Delete 🗑️ |
|---------|--------|---------|---------|------------|
| Company settings | — (one per company) | `view.php?kind=settings&id=` | `edit.php?kind=settings&id=` | POST `delete.php` |
| Business hours | `create.php?kind=business_hour` | `view.php?kind=business_hour&id=` | `edit.php?kind=business_hour&id=` | POST `delete.php` |
| Visit reasons | `create.php?kind=visit_reason` | `view.php?kind=visit_reason&id=` | `edit.php?kind=visit_reason&id=` | POST `delete.php` |
| Appointment types | ➕ `create.php?kind=appointment_type` | `view.php?kind=appointment_type&id=` | `edit.php?kind=appointment_type&id=` (active only) | 🗑️ all rows; core disabled in UI + blocked in `delete.php`; custom types hard-deleted when unreferenced |

- Flash messages via `?msg=` query string after redirect.
- Company settings hub table columns: **Timezone**, **Slot (min)**, **Active**, **Actions** — modality is configured on **business hours** rows only.
- Actions column: `itm-actions-cell` + `data-itm-actions-origin="1"`.

### Other entry files

- **create.php** — visit reason or business hour (day dropdown skips days already present); `active` uses `itm-checkbox-control` + `itm-check-indicator` double-label pattern.
- **edit.php** — POST saves per `kind`; settings, business hour, visit reason, and appointment type forms include compliant `active` checkbox markup.
- **view.php** — read-only detail per `kind`.
- **delete.php** — POST only (`kind`, `id`, CSRF).
- **list_all.php** — redirects to `index.php` (scaffold compatibility).
- **aps_init.php** — shared bootstrap, shell, action cell helpers.

### Not flattened scaffold CRUD

- No single-table `index.php` search/pagination/import; multi-entity hub is intentional.

## 6. API Actions

N/A — HTML forms only. Booking API reads configuration tables indirectly via `includes/itm_appointment.php`.

## 7. File Structure

- **index.php** — configuration hub (tables only)
- **create.php**, **edit.php**, **view.php**, **delete.php** — per-entity CRUD
- **aps_init.php** — bootstrap + layout helpers
- **includes/itm_appointment_settings_admin.php** — ensure defaults, admin list helpers (`*_admin` loaders include inactive rows where noted)

## 8. Multi-Tenant Rules

- All queries filter `company_id` from session.
- `appointment_settings` UNIQUE (`company_id`) — one configuration row per tenant.

## 9. Audit Logging Requirements

- `appointment_settings`, `appointment_business_hours`, `appointment_visit_reasons`, `appointment_type`: `trg_{table}_audit_*` in `db/03_triggers.sql`.
- Form POST handlers use prepared UPDATE/INSERT; triggers record changes.

## 10. Common Pitfalls

- Do not document “inline bulk hours grid on index” — per-row **edit.php** replaced the old save-all-hours form.
- `create.php?kind=business_hour` uses numeric **day_of_week** (0=Sun) — easy to misconfigure vs `display_label`.
- No duplicate-name guard on visit reasons — duplicates allowed in schema.
- Appointment Settings link on booking UI is **`itm_is_admin` only** — users with settings RBAC but not admin never see ⚙️ (see Appointment module notes).
- After fresh deploy without `02_data` registry row, run `php scripts/sync_modules_registry.php` so slug `appointment_settings` exists.
- Editing timezone does not automatically fix booking sidebar “(BST)” hardcoding in Appointment `index.php`.

## 11. Examples of Safe Code Patterns

```php
$sql = 'UPDATE appointment_visit_reasons SET name = ?, sort_order = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'siiiii', $name, $sortOrder, $isActive, $employee_id, $reasonId, $company_id);
```

## 12. Module Owner Notes

### Local URL (open in a new browser tab)

[modules/appointment_settings/index.php](http://localhost/it-management/modules/appointment_settings/index.php)

### Regression

Covered by shared appointment verifier (registry + ensure helper):

```bash
php scripts/verify_appointment.php
php -l modules/appointment_settings/index.php
php -l modules/appointment_settings/edit.php
php -l includes/itm_appointment_settings_admin.php
```

### Daily-use improvement backlog (not implemented)

| Area | Gap | Suggested direction |
|------|-----|---------------------|
| Efficiency | No bulk “save all hours” grid | Optional second tab or restore grid edit for weekly tune-ups |
| Visit reasons | No drag sort | Reorder `sort_order` in UI |
| Validation | Duplicate reason names | Optional UNIQUE per company or warn on create |
| Settings | Delete settings row | Hide 🗑️ or confirm + auto re-ensure; gate booking on `active` |
| Types | Edit name disabled | By design — only `active`; document labels in UI copy |
| RBAC | Admin-only ⚙️ on booking | `has_module_access` + `can_edit` on `appointment_settings` |
| Onboarding | New tenant | Ensure runs on first visit; document default Wed–Fri online window |
| Testing | No dedicated script | Optional `verify_appointment_settings.php` for CRUD smoke only |
| UX | `?msg=` flash only | Session flash or banner component matching other modules |

### Related module

- **Appointment booking:** `modules/appointments/AGENT_NOTES.md`
