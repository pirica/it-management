# AGENT_NOTES.md - db/

## 1. Module Purpose
Canonical SQL schema, seed data, and audit triggers for the IT Management System.

## 4. Business Rules (Critical for Agents)
- **Canonical source:** edit `db/01_schema.sql` (DDL), `db/02_data.sql` (DML/seeds), and `db/03_triggers.sql` (triggers) directly.
- **Import order:** `01_schema.sql` → `02_data.sql` → `03_triggers.sql` in **one MySQL session** (`bash scripts/import_database_split.sh`). Numeric prefix matches run order.
- **Boundaries:** DDL in `01_schema.sql`, DML in `02_data.sql`, triggers in `03_triggers.sql`.

## 7. File Structure
- `01_schema.sql` — DDL (`DROP DATABASE`, `CREATE TABLE`, …)
- `02_data.sql` — seed DML (`INSERT`, `UPDATE`, `DELETE`, `SET @replicate_source_company_id`)
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

**Verify after import:** `php scripts/verify_database_schema.php` (130 tables).

## 10. Common Pitfalls
- Importing `03_triggers.sql` before `02_data.sql` fills `audit_logs` during seed load. [Cursor-Valid]
- Separate `mysql` CLI calls drop `@replicate_source_company_id` before replication `INSERT … SELECT`. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Path helpers: `includes/itm_database_sql_source.php`. Catalog: `scripts/SCRIPTS.md`.
