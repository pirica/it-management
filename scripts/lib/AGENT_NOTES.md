# AGENT_NOTES.md - scripts/lib

## 1. Module Purpose
Shared PHP libraries included by maintenance scripts, QA runners, and browser audit tools. Do not duplicate these helpers in individual scripts.

## 7. File Structure
| File | Role |
|------|------|
| `script_browser_nav.php` | ← Scripts index, relative module links, table→module links |
| `script_cli_output.php` | Browser `<pre>` wrapper + nav for CLI-style audits |
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
| `itm_script_test_employee.php` | Disposable `employees` rows for repro/verify scripts (`script-{slug}-{hex}`), snapshot/restore of sensitive columns, audit `@app_*` context, shutdown teardown. `itm_script_test_employee_create()` returns `id`, `username`, `email`, `company_id`, `role_id`, `access_level_id`, `employment_status_id` (no deprecated `employees.active`). |
| `itm_force_delete_company.php` | Shared tenant wipe used by `scripts/force_delete_company.php` and PHPUnit teardown (`itm_force_delete_company()` deletes all `company_id` rows then the `companies` row). |
| `itm_email_script_helpers.php` | Shared `itm_email_script_resolve_company_id()` for email test scripts and browser/CLI `--company=` parsing (session fallback, default company `1`). |
| `itm_benchmark_sidebar_access.php` | Benchmark functions for measuring sidebar query performance. |

## 4. Business Rules (Critical for Agents)
- New shared script code belongs here when used by two or more scripts.
- Browser reports must use `itm_script_browser_nav_echo()` — never hand-build module URLs with `BASE_URL`.
- Cross-platform env vars: parent scripts use `putenv()`, not `VAR=val php …` inline.

## 10. Common Pitfalls
- Do not link phpMyAdmin from libs — only from `scripts/scripts.php`. [Cursor-Valid]
- `index.html` prevents directory listing; keep it when adding folders. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Full catalog and checklist: `scripts/SCRIPTS.md`.
