# AGENT_NOTES.md - Explorer Tests

## 1. Module Purpose
Unit/regression tests for `modules/explorer/`.

## 3. Required Relationships
- Production docs: `modules/explorer/AGENT_NOTES.md`.
- Upload/hardening reference: `scripts/AGENT_NOTES.md`.
- Database fixtures: `database.sql`.

## 7. File Structure
- `ExplorerTest.php` — `get_full_path()` ACL (`testGetFullPathSecurity` uses `$employeeId`, not `$userId`); hidden listing entries (`testHiddenSystemEntries`); preview routing (`testPreviewModeRouting`); trash leaf filter (`testTrashListFiltersAncestorFolders` for `explorer_filter_trash_list_to_leaf_items()`).

## 10. Common Pitfalls
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users. [Cursor-Valid]
- `ExplorerTest` requires DB via `config.php`; run with full PHPUnit config: `php phpunit/phpunit.phar -c phpunit/phpunit.xml --filter ExplorerTest`. [Cursor-Valid]
- Path-logic-only checks without DB: `php scripts/test_explorer_paths.php`. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Add or update tests when fixing Explorer ACL, trash listing, `downloadZip`, or `.htaccess` hardening; list commands in PR descriptions. Trash leaf filter: `ExplorerTest::testTrashListFiltersAncestorFolders`.
