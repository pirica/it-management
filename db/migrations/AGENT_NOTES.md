# AGENT_NOTES.md - db/migrations/

## 1. Module Purpose

Incremental DDL/DML scripts for **existing** databases that predate the current `db/01_schema.sql` bundle. **Fresh installs** import `db/01_schema.sql` → `db/02_data.sql` → `db/03_triggers.sql` only (`bash scripts/import_database_split.sh`).

Historical migration SQL files were **pruned** once live databases matched canonical schema and rows were recorded in `schema_migrations`. Upgrade history remains in the `schema_migrations` audit table and git history; new schema changes ship in `db/01_schema.sql` (+ optional new migration file until the next prune).

## 4. Business Rules (Critical for Agents)

- **Naming:** `db/migrations/{module}_{subject}.sql` (lowercase module slug, underscore subject).
- **No `ALTER TABLE` in migrations (hard rule):** copy the current table definition from `db/01_schema.sql`, apply the change in the migration file, ship **full `CREATE TABLE`** via `DROP TABLE IF EXISTS` + `CREATE TABLE`.
- **Pair every migration with canonical schema:** mirror the final shape in `db/01_schema.sql` (and `db/02_data.sql` when seeds change) in the **same PR**.
- **Runner:** `php scripts/migrate.php --status` probes the **live database** for every `*.sql` file (except bootstrap). `php scripts/migrate.php --apply` runs SQL only when the probe fails; satisfied migrations are **recorded** without re-executing destructive files. **`schema_migrations`** table is audit/history only.
- **Prune applied migrations:** when all environments show **0 Pending** and canonical `db/` matches live schema, delete obsolete `db/migrations/*.sql` files (keep `schema_migrations.sql` bootstrap). Record applied files with `--apply` before delete. Browser Admin 🗑️ on [migrate.php?run=1](http://localhost/it-management/scripts/migrate.php?run=1) deletes one file at a time.
- **No audit triggers** on private-data tables listed in `AGENTS.md` → Private data — no audit trail.

## 7. File Structure

| File | Role |
|------|------|
| `appointment_settings_booking_enabled.sql` | `appointment_settings.booking_enabled` — tenant booking on/off (migrates prior `active` into `booking_enabled`) |
| `api_v2_scopes.sql` | Scoped integration keys (`api_key_scopes` per `ui_configuration_id` + `scope_slug`); pairs with `includes/itm_api_v2_scopes.php` and Settings API v2 scope checkboxes. |
| `hotel_booking_last_rooms.sql` | Last-room snapshot table (`booking_id` + room/hotel/type/floor fields) |
| `problem_management.sql` | `problems`, `problem_ticket_links`, `known_errors` — Problem Management + Known Error DB (destructive DROP+CREATE; audit triggers in `db/03_triggers.sql`) |
| `problem_master_ticket.sql` | `master_tickets`, `master_ticket_updates`, `problems.master_ticket_id` — cross-company master ticket rollup (destructive DROP+CREATE for problem tables + new master tables) |
| `saved_report_views.sql` | `saved_report_views` + `scheduled_reports.saved_view_id` — saved list views for custom report builder |
| `short_url.sql` | `short_urls`, `short_url_clicks`, `short_url_settings`, `qr_codes.short_url_id` — Short URLs module + QR back-link (destructive to `qr_codes` / `qr_code_scans`) |

## 12. Module Owner Notes

- Operator UI: [migrate.php?run=1](http://localhost/it-management/scripts/migrate.php?run=1) (Admin) · verify: [verify_db_migrations.php?run=1](http://localhost/it-management/scripts/verify_db_migrations.php?run=1)
- Module CRUD (audit history): [schema_migrations/index.php](http://localhost/it-management/modules/schema_migrations/index.php)
- Catalog pointer: `AGENTS.md` → Database & Schema Rules → **Incremental migrations (`db/migrations/`)**
