# Scripts matrix destroy log

Append-only log for the **Destroy -> document -> fresh clone** protocol in `scripts/SCRIPTS_TEST_MATRIX.md`.

When a script wrecks the local `itmanagement` database or critical trees, add a dated entry **before** restoring from `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`, then mark the matrix run status `DESTROYED_ENV`.

## Template

```md
### YYYY-MM-DDTHH:MM:SSZ - `scripts/<name>.php`
- **Command:** `php scripts/<name>.php …`
- **Tier:** N
- **Symptom:** …
- **Exit / tail:** …
- **Restore:** fresh `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` import via mysql CLI or `bash scripts/verify_database_sql_import.sh`
- **Repo files restored:** none | `git checkout -- path…`
- **Resumed at:** next script after culprit
```

## Entries

(none yet)
