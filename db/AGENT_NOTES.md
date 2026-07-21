# AGENT_NOTES.md - db/

## 1. Module Purpose
Canonical SQL schema, seed data, and audit triggers for the IT Management System.

## 4. Business Rules (Critical for Agents)
- **Canonical source:** edit `db/01_schema.sql` (DDL), `db/02_data.sql` (DML/seeds), and `db/03_triggers.sql` (triggers) directly — ship schema changes in those files first; mirror them in `db/migrations/` only for existing databases.
- **Import order:** `01_schema.sql` → `02_data.sql` → `03_triggers.sql` in **one MySQL session** (`bash scripts/import_database_split.sh`). Numeric prefix matches run order.
- **Boundaries:** DDL in `01_schema.sql`, DML in `02_data.sql`, triggers in `03_triggers.sql`.
- **Incremental migrations:** `db/migrations/{module}_{subject}.sql` — copy/paste `DROP TABLE IF EXISTS` + full `CREATE TABLE` from `db/01_schema.sql` (no `ALTER TABLE`, no `_new` staging). See `db/migrations/AGENT_NOTES.md`.
- **`employees` TOTP (vault 2FA):** `totp_secret` (`TEXT`, encrypted at rest in PHP via `itm_totp_encrypt_secret()`), `totp_enabled` (`TINYINT(1) NOT NULL DEFAULT 0`) immediately after `vault_key_hash` in `01_schema.sql`. Live migration: `db/migrations/employee_totp.sql` (destructive `DROP` + `CREATE` — back up or re-import `02_data.sql` after apply).
- **QR share (private-data exempt):** unified `share_sessions` (`module_slug`, `record_id`, optional `scope_path` / `scope_path_hash` for Explorer). Company enable/disable matrix: `company_module_share` + `modules/share_modules/`. Defined in `01_schema.sql`; no rows in `02_data.sql` for `share_sessions`; `company_module_share` seeded in `02_data.sql`; no audit triggers on `share_sessions` in `03_triggers.sql`. Live migration from per-module `*_share_sessions` tables: `db/migrations/share_sessions_unified.sql`.

## 7. File Structure
- `01_schema.sql` — DDL (`DROP DATABASE`, `CREATE TABLE`, …)
- `02_data.sql` — seed DML (`INSERT`, `UPDATE`, `DELETE`, `SET @replicate_source_company_id`)
- `02_data_sample.sql` — **runtime-only** Add sample data / MBQA `sample_data` templates (company `1` markers in file; seeder stamps active tenant). **Not** in import order. Build: `php scripts/extract_02_data_sample.php --apply`.
- `03_triggers.sql` — audit triggers + `SET FOREIGN_KEY_CHECKS=1`
- `index.html` — directory listing prevention

## 8. Import (Laragon / local)

**Preferred (single session):**

```bash
bash scripts/import_database_split.sh
```

**Manual (one piped session only — use `bash scripts/import_database_split.sh` when possible):**

```cmd
cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
(type db\01_schema.sql & echo. & type db\02_data.sql & echo. & type db\03_triggers.sql) | "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -pitmanagement --default-character-set=utf8mb4
```

Do **not** run schema, data, and triggers as three separate `mysql` CLI imports.

**Verify after import:** `php scripts/verify_database_schema.php` (133 tables — count derived from `CREATE TABLE` lines in `01_schema.sql`).

## 10. Common Pitfalls
- Importing `03_triggers.sql` before `02_data.sql` fills `audit_logs` during seed load. [Cursor-Valid]
- Separate `mysql` CLI calls drop `@replicate_source_company_id` before replication `INSERT … SELECT`. [Cursor-Valid]
- Multi-company seed `employees` (companies 2–5) subquery `employment_status_id` / `access_level_id` **before** the late `@replicate_source_company_id` block — an early `access_levels` + `employee_statuses` replication block must run immediately before that `employees` INSERT in `02_data.sql`. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Path helpers: `includes/itm_database_sql_source.php`. Catalog: `scripts/SCRIPTS.md`.
