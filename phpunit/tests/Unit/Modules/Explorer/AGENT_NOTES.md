# AGENT_NOTES.md - Explorer Tests

## 1. Module Purpose
Unit/regression tests for `modules/explorer/`.

## 3. Required Relationships
- Production docs: `modules/explorer/AGENT_NOTES.md`.
- Upload/hardening reference: `docs/file_upload_modules.md`.
- Database fixtures: `database.sql`.

## 7. File Structure
- `ExplorerTest.php` — `get_full_path()` ACL (`testGetFullPathSecurity`).

## 10. Common Pitfalls
- `ExplorerTest` requires DB via `config.php`; run with full PHPUnit config: `php phpunit/phpunit.phar -c phpunit/phpunit.xml --filter ExplorerTest`.
- Path-logic-only checks without DB: `php scripts/test_explorer_paths.php`.

## 12. Module Owner Notes (Optional)
Add or update tests when fixing Explorer ACL or `.htaccess` hardening; list commands in PR descriptions.
