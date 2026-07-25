# AGENT_NOTES.md - .github

## 1. Module Purpose
GitHub configuration for CI and repository automation.

## 7. File Structure
- **workflows/** — GitHub Actions YAML (see `workflows/AGENT_NOTES.md`).

## 10. Common Pitfalls
- Smoke workflow runs only `php -l`, CSRF coverage, and SQLi coverage — not full module browser QA. [Cursor-Invalid]
- Do not add Composer/npm steps; project has no Composer dependency management. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Canonical smoke definition: `scripts/smoke_test.sh` and `scripts/SCRIPTS.md`.
