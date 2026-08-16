# AGENT_NOTES.md - Schema Migrations

## 1. Module Purpose

Read-only-style admin visibility into the **`schema_migrations`** history table populated by `scripts/migrate.php`. Records which `db/migrations/*.sql` files were satisfied (`filename`, `checksum`, `applied_at`).

**Authoritative applied state** is the live schema probe in `migrate.php --status`, not this table alone — satisfied migrations may be recorded without re-executing destructive SQL.

## 2. Key Tables

- **schema_migrations** — global migration audit (no `company_id`)

## 3. Required Relationships

- None — not tenant-scoped. `filename` is unique (`uq_schema_migrations_filename`).

## 4. Business Rules (Critical for Agents)

- **Not multi-tenant:** table has no `company_id`; flattened CRUD still loads under session but rows are global DB history.
- Do not hand-edit checksums to force re-apply — use `migrate.php` and fix probes in `scripts/migrate.php` / migration SQL.
- `schema_migrations.sql` bootstraps this table only; it is excluded from the runner apply loop.
- Canonical schema changes still require mirroring `db/01_schema.sql` in the same PR as `db/migrations/{module}_{subject}.sql`.

## 5. UI Behavior Requirements

Flattened scaffold CRUD on `schema_migrations`:

- List shows `filename`, `checksum`, `applied_at`; no `company_id` column.
- Bulk delete / clear table are destructive to migration history — avoid on production without ops approval.
- `create.php` / manual inserts are rarely needed; prefer CLI `php scripts/migrate.php --apply`.

## 9. Audit Logging Requirements

- **No** `trg_schema_migrations_audit_*` triggers (system maintenance table).

## 10. Common Pitfalls

- Treating missing rows here as “migration not applied” without running `migrate.php --status` against live schema.
- Applying migrations with `DROP TABLE` without backup — see `db/migrations/AGENT_NOTES.md`.

## 12. Module Owner Notes

- Runner: `php scripts/migrate.php --status` / `--apply`
- Migration authoring: `db/migrations/AGENT_NOTES.md`
- Browser catalog: [migrate.php?run=1](http://localhost/it-management/scripts/migrate.php?run=1) (Admin session)
