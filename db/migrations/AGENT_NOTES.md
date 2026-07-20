# AGENT_NOTES.md - db/migrations/

## 1. Module Purpose
Incremental DDL scripts for **existing** databases. Fresh installs use the matching `CREATE TABLE` blocks in `db/01_schema.sql` (and seeds in `db/02_data.sql` when needed).

## 4. Business Rules (Critical for Agents)
- **Naming:** `db/migrations/{module}_{subject}.sql` (lowercase module slug, underscore subject). Examples: `todo_vault.sql`, `notes_vault.sql`.
- **No `ALTER TABLE` in migrations (hard rule):** copy the current table definition from `db/01_schema.sql`, paste it into the migration file, apply the change, and ship the **full `CREATE TABLE`** block. Do not use `ALTER TABLE` / `MODIFY` / `ADD COLUMN` in `db/migrations/`.
- **Live apply pattern (data-preserving):** when the table already has rows, use a swap table in the same migration file:
  1. `SET FOREIGN_KEY_CHECKS = 0`
  2. `CREATE TABLE \`{table}_new\` ( … full target definition … );`
  3. `INSERT INTO \`{table}_new\` ( … ) SELECT … FROM \`{table}\`;` — backfill new columns in the `SELECT` (e.g. `SHA2(TRIM(title), 256)` for `title_hash` on legacy plaintext).
  4. `DROP TABLE \`{table}\`;`
  5. `RENAME TABLE \`{table}_new\` TO \`{table}\`;`
  6. `SET FOREIGN_KEY_CHECKS = 1`
- **Pair every migration with canonical schema:** mirror the same table shape in `db/01_schema.sql` (and `db/02_data.sql` when seeds change) in the **same PR**.
- **Apply order:** run migrations manually on live DBs in filename order; there is no migration runner yet.
- **No audit triggers** on private-data tables listed in `AGENTS.md` → Private data — no audit trail.

## 7. File Structure
- `{module}_{subject}.sql` — one focused table change set per file (full `CREATE TABLE`, not `ALTER`)
- `index.html` — directory listing prevention

## 12. Module Owner Notes (Optional)
Catalog pointer: `AGENTS.md` → Database & Schema Rules → **Incremental migrations (`db/migrations/`)**.
