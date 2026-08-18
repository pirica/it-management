# AGENT_NOTES.md - GitHub Workflows

## 1. Module Purpose
CI pipelines executed on push/PR.

## 7. File Structure
- **smoke.yml** — **smoke** job: `bash scripts/smoke_test.sh` (PHP lint, CSRF audit, SQLi audit). **database-import** job: MySQL 8.0 service on port **3306** with job `env` `MYSQL_PORT=3306` (import script default is **3307** for Dunebox), then `verify_database_sql_import.sh` and `verify_crud_fk_label_search.php`. **tier2** job: `php scripts/run_tier2_checks.php` (24 static gates from `SCRIPTS_TEST_MATRIX.md`). **phpunit** job: `ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php` (883 tests; DB integration cases skipped in CI until a MySQL service is wired for PHPUnit).

## 4. Business Rules (Critical for Agents)
- Keep workflow aligned with `scripts/smoke_test.sh`; do not expand smoke scope in YAML without updating `SCRIPTS.md` and `AGENTS.md` pointers.

## 10. Common Pitfalls

- Do not grow `smoke.yml` scope without updating `scripts/smoke_test.sh` and `scripts/SCRIPTS.md`. [Cursor-Valid]
- Keep CI PHP 7.4 aligned with Cloud Agent / Laragon ITM PHP. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
PHP 7.4 on Ubuntu; matches Cloud Agent smoke instructions.
