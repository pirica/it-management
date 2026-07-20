# Split database import

Canonical schema source remains [`database.sql`](../database.sql) at the repository root. The files in this folder are **generated** from it for ordered imports.

## Files

| File | Contents |
|------|----------|
| `01_schema.sql` | `DROP DATABASE`, `CREATE DATABASE`, all `CREATE TABLE` / `DROP TABLE` DDL |
| `03_data.sql` | Seed `INSERT` / `INSERT IGNORE` / `INSERT … SELECT`, `UPDATE`, `DELETE`, and `SET @replicate_source_company_id` |
| `02_triggers.sql` | Audit `CREATE TRIGGER` blocks |

Regenerate after editing `database.sql`:

```bash
php scripts/split_database_sql.php --apply
php scripts/verify_database_split_parity.php
```

## Import order (mandatory)

Use **one MySQL session** so `@replicate_source_company_id` persists from `03_data.sql`:

1. `database/01_schema.sql`
2. `database/03_data.sql`
3. `database/02_triggers.sql`

Triggers load **after** seed data so bootstrap `INSERT`s do not populate `audit_logs` the way a monolithic import does.

### Laragon / CLI

```bash
bash scripts/import_database_split.sh
```

Or pipe manually:

```bash
mysql -u root -pitmanagement --default-character-set=utf8mb4 < database/01_schema.sql
mysql -u root -pitmanagement --default-character-set=utf8mb4 < database/03_data.sql
mysql -u root -pitmanagement --default-character-set=utf8mb4 < database/02_triggers.sql
```

Separate `mysql` invocations only work if each client reuses the same server session (unreliable). Prefer `import_database_split.sh` or a single concatenated pipe.

### phpMyAdmin

Import the three files **in order** in the same browser session without closing the connection between uploads.

## Verification

```bash
php scripts/verify_database_split_parity.php
```

Expect **130** tables and **337** triggers matching `database.sql`.
