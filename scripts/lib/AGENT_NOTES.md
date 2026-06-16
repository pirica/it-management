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

## 4. Business Rules (Critical for Agents)
- New shared script code belongs here when used by two or more scripts.
- Browser reports must use `itm_script_browser_nav_echo()` — never hand-build module URLs with `BASE_URL`.
- Cross-platform env vars: parent scripts use `putenv()`, not `VAR=val php …` inline.

## 10. Common Pitfalls
- Do not link phpMyAdmin from libs — only from `scripts/scripts.php`.
- `index.html` prevents directory listing; keep it when adding folders.

## 12. Module Owner Notes (Optional)
Full catalog and checklist: `scripts/SCRIPTS.md`.
