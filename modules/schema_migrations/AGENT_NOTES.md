# AGENT_NOTES.md - Schema Migrations

## 1. Module Purpose

Admin-only **read-only** UI for the **`schema_migrations`** history table populated by `scripts/migrate.php`. Records which `db/migrations/*.sql` files were satisfied (`filename`, `checksum`, `applied_at`).

**Authoritative applied state** is the live schema probe in `migrate.php --status`, not this table alone — satisfied migrations may be recorded without re-executing destructive SQL.

## 2. Key Tables

- **schema_migrations** — global migration audit (no `company_id`)

## 3. Required Relationships

- None — not tenant-scoped. `filename` is unique (`uq_schema_migrations_filename`).

## 4. Business Rules (Critical for Agents)

- **Admin only:** `itm_is_admin()` gate on `index.php` and `view.php`; slug in `itm_crud_rbac_exempt_module_slugs()`.
- **Not multi-tenant:** no `company_id` column; list is global DB history.
- Do not hand-edit checksums to force re-apply — use `migrate.php` and fix probes in migration SQL / `scripts/lib/itm_verify_db_migrations_report.php`.
- `schema_migrations.sql` bootstraps this table only; it is excluded from the runner apply loop but is **recorded** in audit history via `itm_database_migrations_record_bootstrap_audit_rows()` when the table exists.
- Canonical schema changes still require mirroring `db/01_schema.sql` in the same PR as optional `db/migrations/{module}_{subject}.sql`.

## 5. UI Behavior Requirements

Bespoke read-only module (`docs/list_bespoke_UI.txt`):

- **index.php** — summary KPIs from `itm_database_migrations_build_status()`, searchable/sortable list (`filename`, `checksum`, `applied_at`), links to [migrate.php?run=1](http://localhost/it-management/scripts/migrate.php?run=1) and [verify_db_migrations.php?run=1](http://localhost/it-management/scripts/verify_db_migrations.php?run=1), per-row Open SQL when file still on disk, drift / file-removed badges.
- **view.php** — read-only detail with checksum comparison when file exists.
- **create.php / edit.php / delete.php / list_all.php** — redirect to `index.php` (no UI mutations).
- **No** bulk delete, Clear Table, sample data, or Excel import.

## 6. Navigation

- Sidebar: Admin → Schema Migrations (`includes/ui_config.php`).
- Registry: `modules_registry` row `schema_migrations`, `is_system_module = 1`.

## 9. Audit Logging Requirements

- **No** `trg_schema_migrations_audit_*` triggers (system maintenance table).

## 10. Common Pitfalls

- Treating missing rows here as “migration not applied” without running `migrate.php --status` against live schema.
- Applying migrations with `DROP TABLE` without backup — see `db/migrations/AGENT_NOTES.md`.

## 12. Module Owner Notes

- Runner: `php scripts/migrate.php --status` / `--apply`
- Regression: `php scripts/verify_schema_migrations_module.php`
- Migration authoring: `db/migrations/AGENT_NOTES.md`
- Browser: [schema_migrations/index.php](http://localhost/it-management/modules/schema_migrations/index.php) (Admin session)
