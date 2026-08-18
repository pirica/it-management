# AGENT_NOTES.md - Schema Migrations

## 1. Module Purpose

Admin-only UI for the **`schema_migrations`** history table populated by `scripts/migrate.php`. Records which `db/migrations/*.sql` files were satisfied (`filename`, `checksum`, `applied_at`).

**Authoritative applied state** is the live schema probe in `migrate.php --status`, not this table alone — satisfied migrations may be recorded without re-executing destructive SQL.

## 2. Key Tables

- **schema_migrations** — global migration audit (no `company_id`)

## 3. Required Relationships

- None — not tenant-scoped. `filename` is unique (`uq_schema_migrations_filename`).

## 4. Business Rules (Critical for Agents)

- **Admin only:** `itm_is_admin()` gate on `index.php`, `view.php`, and `delete.php`; slug in `itm_crud_rbac_exempt_module_slugs()`.
- **Not multi-tenant:** no `company_id` column; list is global DB history.
- **No manual create or edit:** there is no `create.php` or `edit.php`. New history rows come from `migrate.php --apply` / bootstrap recording only.
- **Delete audit rows (Admin):** `delete.php` POST removes one `schema_migrations` history row via `itm_database_migrations_delete_audit_row_by_id()`. This does **not** change live schema and does **not** remove files from `db/migrations/`. To delete a migration file from disk, use **migrate.php** browser 🗑️ or CLI.
- Do not hand-edit checksums to force re-apply — use `migrate.php` and fix probes in migration SQL / `scripts/lib/itm_verify_db_migrations_report.php`.
- `schema_migrations.sql` bootstraps this table only; it is excluded from the runner apply loop but is **recorded** in audit history via `itm_database_migrations_record_bootstrap_audit_rows()` when the table exists.
- Canonical schema changes still require mirroring `db/01_schema.sql` in the same PR as optional `db/migrations/{module}_{subject}.sql`.

## 5. UI Behavior Requirements

Bespoke admin module (`docs/list_bespoke_UI.txt`):

- **index.php** — summary KPIs from `itm_database_migrations_build_status()`, searchable/sortable list of **all** `db/migrations/*.sql` files (live probe status + audit metadata when recorded). **🔎** View works for every file (`view.php?filename=` or `?id=`). **💾** Record inserts an audit row when the live probe already passes (no SQL re-run). **🗑️** delete only when an audit row exists. **Applied at** shows the audit timestamp when recorded, or `Probe satisfied` when applied but not yet logged.
- **view.php** — read-only detail by audit `id` or `filename`; probe status, checksum comparison, **💾** record, **🗑️** delete when recorded.
- **record.php** — POST handler (CSRF, admin) calling `itm_database_migrations_record_satisfied_file()`.
- **delete.php** — POST handler only (CSRF, admin gate, hard `DELETE` on `schema_migrations` by `id`).
- **list_all.php** — redirect to `index.php`.
- **No** `create.php` / `edit.php`, bulk delete, Clear Table, sample data, or Excel import.

## 6. Navigation

- Sidebar: Admin → Schema Migrations (`includes/ui_config.php`).
- Registry: `modules_registry` row `schema_migrations`, `is_system_module = 1`.

## 9. Audit Logging Requirements

- **No** `trg_schema_migrations_audit_*` triggers (system maintenance table).

## 10. Common Pitfalls

- Treating missing rows here as “migration not applied” without running `migrate.php --status` against live schema.
- Expecting delete in this module to roll back DDL — it only drops the audit row.
- Applying migrations with `DROP TABLE` without backup — see `db/migrations/AGENT_NOTES.md`.

## 12. Module Owner Notes

- Runner: `php scripts/migrate.php --status` / `--apply`
- Regression: `php scripts/verify_schema_migrations_module.php`
- Migration authoring: `db/migrations/AGENT_NOTES.md`
- Browser: [schema_migrations/index.php](http://localhost/it-management/modules/schema_migrations/index.php) (Admin session)
