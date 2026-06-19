# AGENT_NOTES.md - Docs

## 1. Module Purpose
Contains system documentation, including architecture diagrams, README images, installation guides, and upload/hardening reference.

## 7. File Structure
- **readme/** — images used in the main project README.
- **`bolt.md`** — Bolt performance journal (critical learnings only; not routine optimization logs). Sidebar module-access benchmark: `php scripts/benchmark_sidebar_module_access.php` (see **`scripts/SCRIPTS.md` → Sidebar module-access benchmark**).
- **`API-Auth_Validation_Tenant-Scoping.md`** — API audit; §5.1 lists implemented controls (including remediated employee_companies BAC, users tenant scoping, CRUD RBAC on flattened index handlers), §5.2 remaining follow-ups (architecture/hardening only). Per-finding markdown under `docs/findings/` was removed after fixes landed; regressions live in `scripts/repro_*.php`, `scripts/check_crud_rbac_coverage.php`, and `scripts/SCRIPTS.md`.
- **PHPUNIT_PLAN.md** — phased plan for expanding PHPUnit HTML coverage (`includes/`, `scripts/`, then module functional pilots). Canonical implementation checklist; see also `phpunit/tests/PREFERENCES.md` and `scripts/SCRIPTS.md`.

## 12. Module Owner Notes (Optional)
Refer to this directory for high-level visual understanding of the database schema and request flow. Run listed `php scripts/verify_*.php` and `php scripts/repro_*.php` regressions before changing deferred security docs.
