# AGENT_NOTES.md - db/

## 1. Module Purpose
Generated SQL import bundles split from canonical `database.sql` at the repository root.

## 4. Business Rules (Critical for Agents)
- **Canonical source:** edit `database.sql` only; regenerate split files with `php scripts/split_database_sql.php --apply`.
- **Import order:** `01_schema.sql` → `03_data.sql` → `02_triggers.sql` in **one MySQL session** (`bash scripts/import_database_split.sh`).
- **Parity gate:** `php scripts/verify_database_split_parity.php` after every regeneration (130 tables, 337 triggers, data multiset match).
- **Do not** hand-edit split boundaries (DML belongs in `03_data.sql`, triggers in `02_triggers.sql`, DDL in `01_schema.sql`).

## 7. File Structure
- `01_schema.sql` — DDL (`DROP DATABASE`, `CREATE TABLE`, …)
- `03_data.sql` — seed DML (`INSERT`, `UPDATE`, `DELETE`, `SET @replicate_source_company_id`)
- `02_triggers.sql` — audit triggers + `SET FOREIGN_KEY_CHECKS=1`
- `index.html` — directory listing prevention

## 8. Import (Laragon / local)

**Preferred (single session):**

```bash
bash scripts/import_database_split.sh
```

**Manual (one piped session only — use `bash scripts/import_database_split.sh` when possible):**

```cmd
cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
(type db\01_schema.sql & echo. & type db\03_data.sql & echo. & type db\02_triggers.sql) | "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -pitmanagement --default-character-set=utf8mb4
```

Do **not** run schema, data, and triggers as three separate `mysql` CLI imports.

**Verify after import:** `php scripts/verify_database_schema.php` (130 tables). Alternative full import: root `database.sql`.

## 10. Common Pitfalls
- Importing `02_triggers.sql` before `03_data.sql` fills `audit_logs` during seed load. [Cursor-Valid]
- Separate `mysql` CLI calls drop `@replicate_source_company_id` before replication `INSERT … SELECT`. [Cursor-Valid]
- Filename order (01, 02, 03) is **not** import order — always **01 → 03 → 02**. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Splitter lib: `scripts/lib/itm_database_sql_split.php`. Catalog: `scripts/SCRIPTS.md`.
