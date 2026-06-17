# AGENT_NOTES.md - Docs

## 1. Module Purpose
Contains system documentation, including architecture diagrams, README images, installation guides, and upload/hardening reference.

## 7. File Structure
- **readme/** — images used in the main project README.
- **`file_upload_modules.md`** — canonical map of upload modules, storage paths, and **managed `.htaccess` policy bodies** (`upload`, `deny_http`, `deny_all`). Update when adding upload modules or changing hardening rules.
- **`vulnerability_report_git_reset.md`** — only remaining June 2026 security write-up; deferred BETA status for `reset_git_history.php`. Remediated finding reports were removed after fixes landed; regressions live under `scripts/verify_*.php` and `scripts/repro_*.php`.
- **`API-Auth_Validation_Tenant-Scoping.md`** — API audit; §5.1 lists implemented controls, §5.2 remaining follow-ups (architecture/hardening only).
- **PHPUNIT_PLAN.md** — phased plan for expanding PHPUnit HTML coverage (`includes/`, `scripts/`, then module functional pilots). Canonical implementation checklist; see also `phpunit/tests/PREFERENCES.md` and `scripts/SCRIPTS.md`.

## 12. Module Owner Notes (Optional)
Refer to this directory for high-level visual understanding of the database schema and request flow. Run listed `php scripts/verify_*.php` and `php scripts/repro_*.php` regressions before changing deferred security docs.
