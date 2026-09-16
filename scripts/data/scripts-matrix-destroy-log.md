# Scripts matrix destroy log

Append-only log for the **Destroy -> document -> fresh clone** protocol in `scripts/SCRIPTS_TEST_MATRIX.md`.

When a script wrecks the local `itmanagement` database or critical trees, add a dated entry **before** restoring from `db/01_schema.sql` or the `db/` split bundle, then mark the matrix run status `DESTROYED_ENV`.

## Template

```md
### YYYY-MM-DDTHH:MM:SSZ - `scripts/<name>.php`
- **Command:** `php scripts/<name>.php …`
- **Tier:** N
- **Symptom:** …
- **Exit / tail:** …
- **Restore:** fresh `db/` via `bash scripts/verify_database_sql_import.sh` **or** `bash scripts/import_database_split.sh`
- **Repo files restored:** none | `git checkout -- path…`
- **Resumed at:** next script after culprit
```

## Entries

(none yet)
