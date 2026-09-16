# AGENT_NOTES.md - .github

## 1. Module Purpose
GitHub configuration for CI and repository automation.

## 7. File Structure
- **workflows/** — GitHub Actions YAML (see `workflows/AGENT_NOTES.md`). **smoke.yml** runs **smoke** (no MySQL) and **database-import** (MySQL 8.0 on port **3306**).

## 10. Common Pitfalls
- **database-import** must set `MYSQL_PORT=3306` — `import_database_split.sh` defaults to **3307** (Dunebox). [Cursor-Valid]
- Smoke **smoke** job runs only static audits via `scripts/smoke_test.sh` — not full module browser QA. [Cursor-Valid]
- Do not add Composer/npm steps; project has no Composer dependency management. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Canonical smoke definition: `scripts/smoke_test.sh` and `scripts/SCRIPTS.md`.
