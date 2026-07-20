# AGENT_NOTES.md - database/

## 1. Module Purpose
Generated SQL import bundles split from canonical `database.sql` at the repository root.

## 4. Business Rules (Critical for Agents)
- **Canonical source:** edit `database.sql` only; regenerate split files with `php scripts/split_database_sql.php --apply`.
- **Import order:** `01_schema.sql` → `03_data.sql` → `02_triggers.sql` in one MySQL session (`bash scripts/import_database_split.sh`).
- **Parity gate:** `php scripts/verify_database_split_parity.php` after regeneration.
- **Do not** hand-edit split boundaries (DML belongs in `03_data.sql`, triggers in `02_triggers.sql`).

## 7. File Structure
- `01_schema.sql` — DDL (`DROP DATABASE`, `CREATE TABLE`, …)
- `03_data.sql` — seed DML (`INSERT`, `UPDATE`, `DELETE`, `SET @replicate_source_company_id`)
- `02_triggers.sql` — audit triggers
- `README.md` — import and maintenance notes

## 10. Common Pitfalls
- Importing `02_triggers.sql` before `03_data.sql` fills `audit_logs` during seed load. [Cursor-Valid]
- Separate `mysql` CLI calls drop `@replicate_source_company_id` before replication `INSERT … SELECT`. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Human-readable import guide: `database/README.md`. Splitter lib: `scripts/lib/itm_database_sql_split.php`.
