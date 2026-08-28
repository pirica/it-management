# Software and workstation EOL tracking

Tracks **when things go EOL** for Office, OS versions, a tenant Software catalog, and equipment hardware. Dates surface on equipment, calendar, expiring, dashboard, reports, and optional email.

Illustrative seed dates in `db/02_data.sql` are **not** official vendor lifecycle statements.

## Tables and columns

| Location | Columns | Notes |
|----------|---------|--------|
| `workstation_office` | `build`, `eol_date`, `extended_date`, `esu_date` | UI labels Build, EOL, Extended, ESU |
| `workstation_os_versions` | same | One event per OS product on the calendar |
| `software` | `company_id`, `name` (unique per company), `build`, three dates, audit/soft-delete | Tenant catalog |
| `equipment_software` | `company_id`, `equipment_id` (CASCADE), `software_id` (RESTRICT) | Dates live on **catalog**, not the junction. Unique `(company_id, equipment_id, software_id)` |
| `equipment` | `eol_date`, `extended_date`, `esu_date` | Hardware dates, independent of OS/Office/software |

Column names use `_date` so `itm_is_date_field_name()` formats them. Per-install date overrides on `equipment_software` are **out of scope**.

## UI

- Catalog CRUD: [modules/software/index.php](http://localhost/it-management/modules/software/index.php), [modules/workstation_office/index.php](http://localhost/it-management/modules/workstation_office/index.php), [modules/workstation_os_versions/index.php](http://localhost/it-management/modules/workstation_os_versions/index.php) (open in a new browser tab). List/view hide `company_id`.
- Equipment create/edit: hardware EOL/Extended/ESU plus multi-select **Software catalog** (`software_ids[]`). Sync: `itm_equipment_software_sync()` in `includes/itm_software_eol.php`.
- Equipment view: hardware dates plus read-only inherited Office / OS / linked software dates.

## Calendar, expiring, dashboard, reports, email

- **Calendar:** one event per catalog product (not exploded onto every asset) plus hardware dates. Distinct colours/labels. Gated with `has_module_access()`. [modules/calendar/index.php](http://localhost/it-management/modules/calendar/index.php)
- **Expiring:** hardware sections for all three dates + catalog sections with affected equipment counts. [modules/expiring/index.php](http://localhost/it-management/modules/expiring/index.php)
- **Dashboard `expiring_30d`:** warranty + certificate **and** distinct equipment whose hardware **or** inherited catalog `eol_date` is within 30 days. Extended/ESU are not in this count.
- **Reports:** EOL series on `get_upcoming_maintenance_forecast()`. [modules/reports/index.php](http://localhost/it-management/modules/reports/index.php)
- **Email:** `eol_date` alert rule (default off, 30 days). Extended/ESU are not emailed. Runner: [run_email_alert_rules.php?run=1](http://localhost/it-management/scripts/run_email_alert_rules.php?run=1) (Admin session).

## Helper and regression

- Core: `includes/itm_software_eol.php` (loaded from `config/config.php`).
- Live DB migration (destructive DROP+CREATE): `db/migrations/software_eol.sql`.
- Regression: [verify_software_eol.php?run=1](http://localhost/it-management/scripts/verify_software_eol.php?run=1) (Admin session) or `php scripts/verify_software_eol.php`.
