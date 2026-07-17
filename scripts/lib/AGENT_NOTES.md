# AGENT_NOTES.md - scripts/lib

## 1. Module Purpose
Shared PHP libraries included by maintenance scripts, QA runners, and browser audit tools. Do not duplicate these helpers in individual scripts.

## 7. File Structure
| File | Role |
|------|------|
| `script_browser_nav.php` | ← Scripts index, relative module links, table→module links |
| `script_cli_output.php` | Browser `<pre>` wrapper + nav for CLI-style audits; `itm_script_output_nl()`, `colorText()`, `itm_script_format_status_line()`, `itm_script_echo_list()` |
| `utf8_file.php` | UTF-8 writes for `qa-reports/*.md` / `.json` |
| `mbqa_report_paths.php` | Timestamped QA report paths |
| `mbqa_runner_tiers.php` | Tier D / skipClear canonical lists |
| `mbqa_report_xlsx.php` | Excel report builder from runner JSON |
| `mbqa_build_report_lib.php` | Markdown report build helpers |
| `mbqa_import_helpers.php` | Module browser QA import helpers |
| `mbqa_step_display.php` | Step slug → label mapping |
| `sql_injection_detector.php` | SQLi signature tests |
| `equipment_type_modules.php` | Canonical `is_*` allowlist and cleanup |
| `itm_api_tier_test_helpers.php` | Disposable `ui_configuration` seed/cleanup, browser probe URL (optional `api_key`; Free uses session URL without key), `itm_apitest_publish_http_session()` for keyless HTTP probes, HTTP probe for `apitest_tier_*.php` |
| `itm_script_test_employee.php` | Disposable `employees` rows for repro/verify scripts (`script-{slug}-{hex}`), snapshot/restore of sensitive columns, audit `@app_*` context, shutdown teardown. `itm_script_test_employee_create()` / `delete()` clear stale `@app_employee_id` via `itm_script_test_employee_clear_audit_context()` so `audit_logs` FK does not fail on INSERT/DELETE triggers. `itm_script_test_employee_create_session_actor()` — shared disposable Admin/employee session actors (PHPUnit + browser script isolation). Returns `id`, `username`, `email`, `company_id`, `role_id`, `access_level_id`, `employment_status_id` (no deprecated `employees.active`). |
| `itm_force_delete_company.php` | Shared tenant wipe used by `scripts/force_delete_company.php` and PHPUnit teardown (`itm_force_delete_company()` deletes all `company_id` rows then the `companies` row). |
| `itm_email_script_helpers.php` | Shared `itm_email_script_resolve_company_id()` for email test scripts and browser/CLI `--company=` parsing (session fallback, default company `1`). |
| `itm_apply_script_bootstrap.php` | Shared bootstrap for `scripts/apply*.php` (dry-run default, Admin browser gate on `?apply=1` only, target lists) |
| `itm_schema_validation.php` | `itm_schema_collect_validation_issues()` — employee_id FK, duplicate index, and orphaned index checks; used by `schema_report.php` |
| `itm_script_bootstrap.php` | Global `scripts/*` contract (loaded from `config.php`): browser test-session swap (`itm_script_begin_browser_isolated_session()` after `itm_is_admin()`), `csrf_token` copy/sync/merge (`itm_script_sync_csrf_to_browser_session_backup()`, `itm_script_finish_browser_isolated_session()`), `itm_script_session_or_authorization_is_admin()`, `itm_script_require_admin_script_or_exit()`, disposable session rejection, `itm_script_with_test_session_context()`, `itm_script_publish_isolated_http_session()`, `itm_script_prepare_cli_entry()` |
| `itm_script_cli_entry.php` | Alias for `itm_script_regression_entry.php` |
| `itm_script_regression_entry.php` | Browser + CLI regressions (`apitest_tier_*.php`): `ITM_CLI_SCRIPT` on CLI only; Admin gate in browser |
| `itm_repro_floor_designer_rce.php` | Floor Designer `save_as_floor_plan` repro: sample PNG from `images/switch_port_icons/`, isolated **CLI** subprocess (`itm_repro_floor_designer_resolve_php_binary()` skips `php-cgi` on Laragon), JSON parse, gallery cleanup |
| `itm_repro_idfs_bac.php` | IDFs `position_delete` BAC repro: seeds `idf_device_type` + `idf_positions` (required `device_type` FK), cross-tenant delete attempt, isolated CLI subprocess, JSON parse |
| `itm_repro_vulnerabilities.php` | `repro_vulnerabilities.php` isolated CLI subprocess helpers (Laragon `php.exe`, session before `config.php`, CSRF mock) |
| `itm_perform_audit.php` | `perform_audit.php` subprocess discovery/exclusions; skips `health.php` (shell bootstrap), session-mock harnesses (`test_ajax.php`, `test_edit.php`), Tier 4/5 maintenance, `repro_*`, `verify_*`, `_tmp_*` |

## 4. Business Rules (Critical for Agents)
- New shared script code belongs here when used by two or more scripts.
- Browser reports must use `itm_script_browser_nav_echo()` — never hand-build module URLs with `BASE_URL`.
- Cross-platform env vars: parent scripts use `putenv()`, not `VAR=val php …` inline.
- Admin-gated `scripts/*` in the browser: after `config.php`, call `itm_script_require_admin_script_or_exit($conn)` — not `itm_is_admin($conn, (int)$_SESSION['employee_id'])` alone (disposable test Admin + pre-swap authorization employee). Catalog `scripts.php` and CLI target-user checks (e.g. `bypass_v2.php`) are exceptions.

## 10. Common Pitfalls
- **Browser POST + CSRF after isolation swap:** `itm_script_begin_browser_isolated_session()` replaces `$_SESSION` with a disposable actor. Without copying `csrf_token` on begin, syncing it via `itm_script_sync_csrf_to_browser_session_backup()` when forms call `itm_get_csrf_token()`, and merging it back in `itm_script_finish_browser_isolated_session()`, GET forms mint a token the restored real session cannot validate on POST. Use `itm_get_csrf_token()` / `itm_require_post_csrf()` in script forms — do not compare `$_POST['csrf_token']` to raw `$_SESSION['csrf_token']` without `?? ''`. Run POST CSRF before any state-changing work in the script body. [Cursor-Valid]
- Do not link phpMyAdmin from libs — only from `scripts/scripts.php`. [Cursor-Valid]
- `index.html` prevents directory listing; keep it when adding folders. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Full catalog and checklist: `scripts/SCRIPTS.md`.
