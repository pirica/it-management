# AGENT_NOTES.md - Docs

## 1. Module Purpose
Contains system documentation, including architecture diagrams, README images, installation guides, and upload/hardening reference.

## 7. File Structure
- **readme/** — images used in the main project README.
- **`file_upload_modules.md`** — canonical map of upload modules, storage paths, and **managed `.htaccess` policy bodies** (`upload`, `deny_http`, `deny_all`). Update when adding upload modules or changing hardening rules.
- **`vulnerability_report_select_api.md`** — Select Options API privilege-escalation finding; remediation tracked in `includes/itm_select_options_policy.php`.
- **PHPUNIT_PLAN.md** — phased plan for expanding PHPUnit HTML coverage (`includes/`, `scripts/`, then module functional pilots). Canonical implementation checklist; see also `phpunit/tests/PREFERENCES.md` and `scripts/SCRIPTS.md`.

## 12. Module Owner Notes (Optional)
Refer to this directory for high-level visual understanding of the database schema and request flow.
