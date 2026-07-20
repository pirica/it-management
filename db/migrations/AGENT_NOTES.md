# AGENT_NOTES.md - db/migrations/

## 1. Module Purpose
Incremental DDL/DML scripts for existing databases. Fresh installs use the matching `CREATE TABLE` / seed blocks in `db/01_schema.sql` and `db/02_data.sql`.

## 4. Business Rules (Critical for Agents)
- **Naming:** `db/migrations/{module}_{subject}.sql` (lowercase module slug, underscore subject). Examples: `todo_vault.sql`, `notes_vault.sql`.
- **Pair every migration with canonical schema:** apply the same column/index changes to `db/01_schema.sql` (and seeds in `db/02_data.sql` when needed) in the **same PR**.
- **Idempotent where practical:** use `IF NOT EXISTS` / conditional patterns when safe; document one-shot `ALTER` when not.
- **Apply order:** run migrations manually on live DBs in filename order; there is no migration runner yet.
- **No audit triggers** on private-data tables listed in `AGENTS.md` → Private data — no audit trail.

## 7. File Structure
- `{module}_{subject}.sql` — one focused change set per file
- `index.html` — directory listing prevention

## 12. Module Owner Notes (Optional)
Catalog pointer: `AGENTS.md` → Database & Schema Rules → **Incremental migrations (`db/migrations/`)**.
