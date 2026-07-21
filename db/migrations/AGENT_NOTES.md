# AGENT_NOTES.md - db/migrations/

## 1. Module Purpose
Incremental DDL scripts for **existing** databases. Fresh installs use the matching `CREATE TABLE` blocks in `db/01_schema.sql` (and seeds in `db/02_data.sql` when needed).

## 4. Business Rules (Critical for Agents)
- **Naming:** `db/migrations/{module}_{subject}.sql` (lowercase module slug, underscore subject). Examples: `explorer_share.sql`, `employee_totp.sql`, `floor_plans_share.sql`.
- **No `ALTER TABLE` in migrations (hard rule):** copy the current table definition from `db/01_schema.sql`, paste it into the migration file, apply the change, and ship the **full `CREATE TABLE`** block. Do not use `ALTER TABLE` / `MODIFY` / `ADD COLUMN` in `db/migrations/`.
- **No staging tables (hard rule):** do not use `{table}_new`, `RENAME TABLE`, or `INSERT … SELECT` swap patterns. Migrations use **copy/paste replacement** only:
  1. `SET FOREIGN_KEY_CHECKS = 0`
  2. `DROP TABLE IF EXISTS \`{table}\`;`
  3. `CREATE TABLE \`{table}\` ( … full target definition copied from `db/01_schema.sql` with your change … );`
  4. `SET FOREIGN_KEY_CHECKS = 1`
- **Data warning:** `DROP TABLE` removes existing rows. Back up or export data before applying on production; re-seed or restore manually when needed.
- **Pair every migration with canonical schema:** mirror the same table shape in `db/01_schema.sql` (and `db/02_data.sql` when seeds change) in the **same PR**.
- **Apply order:** run migrations manually on live DBs in filename order; there is no migration runner yet.
- **No audit triggers** on private-data tables listed in `AGENTS.md` → Private data — no audit trail.

## 7. File Structure
- `{module}_{subject}.sql` — one focused table replacement per file (`DROP TABLE` + full `CREATE TABLE`, not `ALTER`)
- `employee_totp.sql` — `employees` table with `totp_secret` + `totp_enabled` (mirrors `db/01_schema.sql`; destructive — re-seed employees after apply)
- `index.html` — directory listing prevention

## 12. Module Owner Notes (Optional)
Catalog pointer: `AGENTS.md` → Database & Schema Rules → **Incremental migrations (`db/migrations/`)**.
