# AGENT_NOTES.md - phpunit/tests/Unit/Support

## 1. Module Purpose
Shared PHPUnit infrastructure for script CLI subprocess tests and safe extraction of module helper functions without loading full module entry files.

## 4. Business Rules (Critical for Agents)
- **Disposable script test users:** when tests INSERT/UPDATE `employees` or touch `reset_token` / password fields, use `scripts/lib/itm_script_test_employee.php`; never mutate seed user id `1`. See `scripts/SCRIPTS.md` → Disposable script test users.
- **No file-scope requires in test files:** load support classes from `phpunit/tests/bootstrap.php`, not from individual `*Test.php` / `*.unittest.php` files.
- **ItmScriptCliTestTrait:** subprocess runner for `scripts/*.php` that call `exit()` — always use `2>&1` via `runRepoScript()` / `runPhpScriptFile()`.
- **ItmScriptCliTestCase:** extend this (not `TestCase` + trait require) for CLI audit script tests.
- **ItmModuleIsolatedTestTrait:** subprocess `runIsolatedModule()` for module `index.php` handlers — required when a test would `include` the same module twice in one PHPUnit process (avoids `Cannot redeclare` fatals from procedural helpers in `modules/employees/index.php` and similar entry files).

## 7. File Structure
| File | Role |
|------|------|
| `ItmScriptCliTestTrait.php` | `runRepoScript()`, `runPhpScriptFile()` |
| `ItmScriptCliTestCase.php` | Base class for audit `check_*` unittest files |
| `ItmExtractFunctionTestTrait.php` | `requireExtractedFunction()`, `itmExtractFunctionSource()` (brace-balanced) for Org Chart / Explorer tests |
| `ItmModuleIsolatedTestTrait.php` | `runIsolatedModule()` — subprocess include for module entry files |

## 10. Common Pitfalls
- Including audit scripts at file scope halts PHPUnit — subprocess only. [Cursor-Valid]
- Deleting the temp file after `require_once` is safe; the function stays loaded for the request. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Parent: `phpunit/tests/AGENT_NOTES.md`. Consumers: `phpunit/tests/Unit/Scripts/check_*`, `ScriptCatalogSmokeTest.php`, `ReproAuditDisclosureTest.php`, `OrgChartTest.php`, `ExplorerTest.php`.
