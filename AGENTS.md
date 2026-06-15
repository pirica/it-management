# AGENTS.md

> [!IMPORTANT]
> **Role:** You are a Senior PHP Developer maintaining a legacy-style Procedural IT Management System.
> **Constraint:** Follow these rules strictly. Do not refactor to OOP, MVC, or modern frameworks. Keep logic flat and modular.

This document provides essential instructions, architectural constraints, and coding standards for AI agents working on the **IT Management System**.

## ✅ Agent compliance workflow (mandatory)

Before making any change, replying, running commands, editing files, or proposing solutions:

1. **Read `AGENTS.md` completely** at session start (and when resuming after a long gap or context switch).
2. **Stop and ask clarification questions** if any part of `AGENTS.md` is unclear, ambiguous, missing, conflicting, or not fully understood — do not guess or proceed on assumptions.
3. **Update `AGENTS.md` when needed** to include and preserve new process rules or mandatory instructions (ship on a **fresh branch + new PR**; do not fold unrelated feature work into the same PR — see **Change Hygiene → PR review**).
4. **Before every reply**, re-check `AGENTS.md` and confirm the response follows it (architecture, Protection Zone, encoding, scripts catalog, testing guardrails, PR workflow, and any section relevant to the task).
5. **Auto-open fresh PRs (mandatory):** when implementation is complete and required checks pass, ship via **FRESH PR only**: **`git checkout -b <new-branch>`** from synced **`origin/master`** → commit → **one** **`git push -u origin <new-branch>`** (first publish only) → **`gh pr create`** → reply with the **new PR URL**. **Do not ask** the user to confirm (“say so and I will…”, “would you like me to open a PR?”, etc.). There is **no** push to update PR #N. Exceptions: user explicitly asked to hold commits/push, read-only/exploratory session, or no file changes to commit.
6. **Never push to an existing PR branch (hard fail):** agents **do not** `git push` (including `git push`, `git push origin <branch>`, or force-push) to any branch that already has **open or merged PR #N** for this work. Follow-ups, review fixes, and corrections use a **new branch name** + **new push** + **`gh pr create`** → **new PR number**. Forbidden user-facing lines: “Pushed to https://github.com/…/pull/1860”, “updated PR #1860”, “added commits to the open PR”, “you can push after the diff check”.
7. **Never local-only commits (hard fail):** `git commit` without the **first-publish** **`git push -u origin <new-branch>`** and **`gh pr create`** (when there are file changes to ship) is **not done**. Do **not** tell the user work is “committed” if `git status` shows **ahead of origin** or the PR URL is missing. Do **not** suggest “push when you want” — **you** complete the fresh-PR sequence in the same turn.
8. **Pre-push full diff review (hard fail):** before the **only** allowed **`git push -u origin <new-branch>`** on that deliverable, run and **read** the full patch against `origin/master` (see **Change Hygiene → Pre-push diff review**). Do **not** publish a branch whose diff removes unrelated functions, reverts a just-merged fix, or shows large deletions you did not intend.

Only after `AGENTS.md` has been checked, understood, and updated when required may you continue with implementation.

## 🚀 Project Overview
A multi-company IT Asset Management System built with PHP and MySQL.
* **Design Philosophy:** GitHub Copilot theme (Light/Dark mode).
* **Architecture:** Procedural PHP with modular CRUD structures.
* **Multi-tenancy:** Data is strictly scoped by `company_id`.

## 🛠 Tech Stack & Environment
* **Backend:** PHP 7.4.33 (Strictly **MySQLi**, do NOT use PDO).
* **Database:** MySQL 8.0+.
* **Frontend:** Vanilla JS, Custom CSS (`css/styles.css`), No Frameworks.
* **Environment:** Apache 2.4+. **No Composer** dependency management.

## 🧩 Character encoding, locales, and Unicode (mandatory)

This project stores and displays **Unicode** text (including emoji such as 🧩) end-to-end. Do not “fix” mojibake by removing punctuation or emoji—fix the **encoding contract**.

### Database (`database.sql`)

* Database and tables use **`utf8mb4`** with **`utf8mb4_unicode_ci`** (see top of `database.sql`: `CREATE DATABASE … utf8mb4`, `SET NAMES utf8mb4`).
* Application connection must match: `mysqli_set_charset($conn, 'utf8mb4')` in `config/config.php`.
* Never downgrade to `utf8` (3-byte) for new tables—emoji and some symbols need 4-byte UTF-8.

### Repository and source files

* **All** tracked text (`.php`, `.md`, `.sql`, `.js`, `.css`, `.html`) is **UTF-8 without BOM**, except generated QA artifacts below.
* **`.editorconfig`** and **`.gitattributes`** at repo root enforce UTF-8 and LF for agents and editors—do not re-save as Windows-1252 / “ANSI”.
* **Change hygiene:** do not rewrite whole files to change encoding or line endings; see **Change Hygiene Rules**.

### Locales and copy

* **UK English (en-GB)** is the default for UI labels, docs, and agent-written prose (spelling: organisation, colour only when matching existing UI).
* **Portuguese (Portugal) (pt-PT)** is used where the product already ships bilingual or regional copy—match existing tone; do not machine-translate unrelated modules in drive-by edits.
* **Emoji** in UI, `AGENTS.md`, and seed data are allowed when intentional (e.g. 🧩 section markers, toolbar icons in copy).

### HTTP and PHP output

* HTML responses: `Content-Type: …; charset=utf-8` (see `config/config.php` JSON headers and script browser pages).
* `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')` for echoed user data.
* JSON from scripts: `json_encode(…, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` so Portuguese accents and symbols are not `\u` escaped unnecessarily.

### Generated QA reports (`qa-reports/`)

* `module_browser_qa_runner.php` and `module_browser_qa_build_report.php` write **UTF-8** via `scripts/lib/utf8_file.php` (`itm_write_utf8_text_file`).
* **`.md` under `qa-reports/`** may be written **with a UTF-8 BOM** (`EF BB BF`) so **Windows Notepad** opens them correctly.
* **`.json` under `qa-reports/`** is UTF-8 **without BOM** — `json_decode()` rejects a leading BOM; the report builder strips BOM on read if an older file still has one.

### Mojibake troubleshooting (symptom → cause)

| What you see | What it usually means |
|---|---|
| `â€”` instead of `—` | UTF-8 em dash bytes read as Windows-1252 / Latin-1 |
| `â€¦` instead of `…` | UTF-8 ellipsis bytes read as Windows-1252 |
| `Ã©` instead of `é` | UTF-8 accent read as Latin-1 (common in pt-PT strings) |
| `ðŸ§©` instead of 🧩 | UTF-8 emoji read as Latin-1 |

**The file on disk is often already correct UTF-8** (verify with a UTF-8-aware editor or hex: em dash = `E2 80 94`). Fix the **viewer**, not the database:

1. **Cursor / VS Code:** `"files.encoding": "utf8"`; reopen file with encoding **UTF-8**.
2. **PowerShell:** `Get-Content -Encoding utf8 path\to\file.md` (default encoding is not UTF-8 on Windows).
3. **Re-build QA markdown:** `php scripts/module_browser_qa_build_report.php --date=YYYY-MM-DD` after pulling encoding fixes.
4. **Do not** use Notepad “Save as” ANSI or Excel “CSV ANSI” on UTF-8 exports.

**Agents:** never replace `—` / `…` / emoji with ASCII substitutes just to avoid display glitches in one tool; preserve UTF-8 and document viewer settings.

### PR descriptions: shell / quoting corruption (not repo encoding)

GitHub PR titles and bodies are often pasted from the shell (`gh pr create`, `gh pr edit`). On **Windows PowerShell**, inline `--body "..."` strings can **mangle** backticks, `$variables`, and step names before GitHub ever sees them. That is **shell/quoting corruption**, not a UTF-8 or repository encoding defect — do not “fix” it by re-encoding `AGENTS.md` or source files.

| What you see on GitHub | What it usually means |
|---|---|
| `\dd` or `^Gdd` instead of `add` | PowerShell ate or escaped backticks around `add` |
| `\ulk_delete` instead of `bulk_delete` | Leading `b` dropped with broken `` ` `` escaping |
| `\\ >= \\` instead of `$totalRows >= $perPage` | `$` expanded or stripped in the shell |
| Doubled backslashes before step slugs (`\list\`) | Escaped markdown backticks in a quoted string |

**Mandatory workflow (agents):**

1. Write the PR body to a **UTF-8** file in the repo (e.g. `.pr-body-tmp.md` — do not commit; add to `.gitignore` if the file is kept locally).
2. Create or update the PR with **`--body-file`**, not inline `--body`:
   ```bash
   gh pr create --title "Short title" --body-file .pr-body-tmp.md
   gh pr edit <number> --body-file .pr-body-tmp.md
   ```
3. **Verify on GitHub** before marking the PR ready: `gh pr view <number> --json body` and open the PR in the browser — step slugs (`add`, `bulk_delete`, `bulk_cancel`, `list`) and PHP snippets must read literally.
4. If the body is wrong, **`gh pr edit --body-file`** again; do not stack “fixes” as extra broken inline `--body` strings.

On **bash**, prefer a heredoc or `--body-file` when the body contains `` ` ``, `$`, or `>=`. Treat garbled PR text as a **process** bug until verified with step 3.

## 📂 Directory Map
* `config/`: Core settings and `config.php`.
* `includes/`: UI components (headers, sidebars) and utility functions.
* `modules/`: Feature-specific CRUD logic.
* `scripts/`: Maintenance, security audits, and CLI tools. Catalog: `scripts/scripts.php`.
* `js/` & `css/`: Assets (use `css/styles.css`).
* **Required Dirs:** `images/`, `tickets_photos/`, `backups/`, and `files/` must exist with write permissions.
* `scripts/api.php`: API Documentation
* **Mandatory script placement:** All scripts generated to debug, perform fixes, or document findings must be saved in the `scripts/` directory. Every new script added to this directory must be cataloged in `scripts/scripts.php`. These scripts must support execution via both Browser and CLI and include the standard Navigation Menu (relative back link to `scripts/scripts.php`) using `itm_script_browser_nav_echo()` when viewed in a browser.

### Scripts directory (`scripts/`) — mandatory for every tool

The live catalog is **`scripts/scripts.php`**. Before merge, **verify every runnable file under `scripts/`** (and any new script you add) against this checklist.

#### 1. Catalog entry in `scripts/scripts.php` (required for every new script)

Add a table row with:

| Column | Content |
|--------|---------|
| **Script** | Filename (link if browser-safe to open) |
| **Access** | **Browser**, **CLI**, or both; mark **CLI-only** for bash, file writers, or `PHP_SAPI !== 'cli'` guards |
| **What it does** | Plain-language purpose (one short paragraph) |
| **How to use** | Exact browser URL/path, query flags, env vars, and CLI command: `php scripts/<name>.php [options]` |

Do not add a script under `scripts/` without updating `scripts/scripts.php`.

#### 2. Browser scripts (`scripts/*.php` opened in the browser)

* **Back link (required):** Every HTML report must show **← Scripts index** at the top, linking to `scripts/scripts.php` (relative `scripts.php` from `scripts/`).
  * Use `scripts/lib/script_browser_nav.php`: `require_once …/script_browser_nav.php`; then `itm_script_browser_nav_echo()`.
  * Plain-text-in-`<pre>` audits: use `scripts/lib/script_cli_output.php` (`itm_script_output_begin()`), which includes the same nav bar.
* **Human-readable results:** Browser output must explain findings in plain language (not only internal codes). Example: write “Duplicate dropdown option” rather than only `duplicate_dropdown_risk`. Include a short “what to do next” when useful.

#### Link creation rules (browser scripts — mandatory)

All outbound links in HTML script output must use helpers from **`scripts/lib/script_browser_nav.php`**. Do **not** hand-build `<a href="…">` with `BASE_URL`, `itm_script_module_index_url()`, or phpMyAdmin URLs.

| What appears in the report | Create a link? | How (browser) | Example |
|---------------------------|----------------|---------------|---------|
| **← Scripts index** | Always | `itm_script_browser_nav_echo()` | `scripts.php` (relative) |
| **Module folder** (`floor_plans`, `catalogs`, …) | Always | `itm_script_format_module_link('floor_plans')` or `itm_script_format_module_path_link('modules/catalogs/')` | `../modules/floor_plans/index.php` |
| **Database table name** (`catalogs`, `floor_plan_folders`, …) | **Only if** `modules/<table>/index.php` exists | `itm_script_format_table_link($tableName)` | `catalogs` → module link; `floor_plan_folders` → plain text only |
| **phpMyAdmin** | **Only on `scripts/scripts.php`** | Hardcode in catalog: `http://localhost/phpmyadmin/` | Never in other `scripts/*.php` output |
| **Edit row / actions** | When useful | `itm_script_module_relative_href_from_path('modules/name/', 'edit.php?id=5')` + `itm_script_external_link_html()` | `../modules/catalogs/edit.php?id=5` |

**Hard rules:**

1. **Relative paths only** for module links from `scripts/` (`../modules/…`). Never use `BASE_URL` or absolute URLs like `https://localhost/it-management/modules/…` in script reports.
2. **`target="_blank"`** and `rel="noopener noreferrer"` on every external/new-tab link — use `itm_script_external_link_html($href, $label)`.
3. **Table name ≠ module name** → no link. Example: table `floor_plan_folders` is not a module folder; show `<code>floor_plan_folders</code>` or plain text. Table `equipment` matches `modules/equipment/` → link the name to that module.
4. **phpMyAdmin** is documented and linked **only** in **`scripts/scripts.php`** (Laragon local MySQL). Audit/report scripts must not link table names (or anything else) to phpMyAdmin.

* **Exceptions (document in catalog):** JSON-only endpoints (e.g. `test_sql_injection.php`) and CLI entry points that redirect to a UI (e.g. `detect_fk_dropdown_ui_risk.php` → `detect_fk_dropdown_ui_risk_ui.php`) do not need HTML nav on the CLI path.

#### 3. CLI scripts

* **Cross-platform environment variables (mandatory):** Do **not** use bash-style inline assignments like `VAR=val php script.php` in `passthru()` or `exec()` calls. This syntax is not supported by Windows `cmd.exe`. Use **`putenv('VAR=val')`** in the parent PHP script instead; environment variables set via `putenv` are inherited by child processes.
* Run from repository root: `php scripts/<script>.php [options]` (Linux/macOS/CI); on **Windows Laragon** use the **full PHP binary path** — see **Setup & Debugging → PHP CLI tests — full binary path (mandatory)**.
* **Windows Laragon (mandatory for tests):** `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe` — always use this full path when running scripts locally; in **PowerShell** prefix with **`&`**; list the exact shell command in PR test plans (see **Setup & Debugging → PHP CLI tests**).
* **`PHP_BINARY` for sub-processes:** When a script needs to execute another PHP script, prefer using the **`PHP_BINARY`** constant to ensure the same PHP version is used.
* **Destructive or repo-writing tools** (`normalize_database_sql_created_at.php`, `apply_module_sample_data_seed.php`, `apply_*_fix.php`, `repair_table_from_schema.php`, etc.): **CLI-only** — block web SAPI with `PHP_SAPI !== 'cli'` and show a small HTML page with **← Scripts index** + CLI instructions if opened in a browser.
* List exact commands and outcomes in the PR description when checks ran.

#### 4. Shared libraries (do not duplicate ad hoc)

| File | Use |
|------|-----|
| `scripts/lib/script_browser_nav.php` | **← Scripts index**, relative module links, table→module links when folder exists (`target="_blank"`) |
| `scripts/lib/script_cli_output.php` | Wrap browser audit output in `<pre>` + shared nav |
| `scripts/lib/utf8_file.php` | UTF-8 writes for `qa-reports/*.md` and `.json` (optional BOM for Windows viewers) |
| `scripts/lib/mbqa_report_paths.php` | Timestamped `qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json` / `.xlsx` paths; stable `module-browser-qa.md` for build report |
| `scripts/lib/mbqa_runner_tiers.php` | Canonical `$bespokeSmoke` (Tier D) and `$skipClear` lists; tier reference markdown/HTML for build reports |
| `scripts/lib/mbqa_report_xlsx.php` | Builds `qa-reports/module-browser-qa.xlsx` (Summary, All steps, Failures sheets) from runner JSON |
| `scripts/lib/sql_injection_detector.php` | SQLi signature tests (included by matrix / sandbox tools) |
| `scripts/lib/equipment_type_modules.php` | Canonical `modules/is_*` allowlist (`is_switch`, `is_server`, …); safe removal of regression-test scaffold dirs only (`*_itm_eqdct_*`, `*_itm_edct_*`) |

#### Equipment-type façade modules (`modules/is_*`) and clear-table tests

Canonical equipment-type wrappers live under **`modules/is_*`** (for example `is_switch`, `is_server`, `is_workstation`). They delegate to `modules/equipment/` and must **not** be deleted by maintenance scripts.

| Script | Role |
|--------|------|
| `scripts/lib/equipment_type_modules.php` | Shared allowlist + `itm_remove_equipment_regression_test_module_dirs()` / `itm_ensure_canonical_equipment_type_modules()` |
| `scripts/ensure_equipment_type_modules.php` | Verify or recreate missing canonical `modules/is_*/index.php` wrappers (CLI) |
| `scripts/cleanup_equipment_test_module_artifacts.php` | **CLI-only** cleanup utility: remove test `equipment_types` rows (incl. `MBQA-equipment_types-…`), ITM test companies, junk `is_*_itm_eqdct_*` / `is_mbqa_equipment_types_*` folders, sidebar prefs, then re-ensure canonical façades |
| `scripts/equipment_delete_clear_table_test.php` | DB regression for equipment `clear_table` + transactional single delete (use type names **`Switch`** / **`Server`**, not suffixed names) |
| `scripts/employees_delete_clear_table_test.php` | DB regression for employees `clear_table` transaction rollback |
| `scripts/check_equipment_clear_table_delete.php` | Static guard for equipment clear-table helpers (run manually after equipment delete/clear-table changes) |
| `scripts/check_employees_clear_table_transaction.php` | Static guard for employees clear-table transaction (run manually after employees `clear_table` changes) |

**Why tests must not invent new `is_*` folder names:** inserting `equipment_types` named like `Switch itm_eqdct_*` or QA tags `MBQA-equipment_types-…` triggers `itm_ensure_equipment_type_module_scaffold()` in `includes/ui_config.php` and pollutes the sidebar. In the browser, **`module_browser_qa_runner.php`** now runs **`module_clean_tests_qa_runner.php` silently before and after** **Run QA**; for other equipment DB tests, run `php scripts/cleanup_equipment_test_module_artifacts.php` manually.

#### Smoke tests (CI — `scripts/smoke_test.sh`)

GitHub Actions (`.github/workflows/smoke.yml`) and local CI use **`bash scripts/smoke_test.sh`** — **only** these three checks:

| Step | Command | Purpose |
|------|---------|---------|
| 1 | `php -l` on every `*.php` | Syntax lint |
| 2 | `php scripts/check_csrf_coverage.php` | POST handlers / forms have CSRF |
| 3 | `php scripts/check_sql_injection_coverage.php` | SQLi coverage audit |

Other scripts (`check_index_table_compliance.php`, `check_ui_configuration_coverage.php`, `check_display_field_columns_search.php`, employees/equipment clear-table guards, DB regression tests) are **not** part of smoke — run them manually when the change scope requires it (see `scripts/scripts.php`).

Optional DB regression (requires MySQL): `php scripts/employees_delete_clear_table_test.php`, `php scripts/equipment_delete_clear_table_test.php`.

Module seed expansion in `database.sql` (repo write, no DB mutation): `php scripts/apply_module_sample_data_seed.php --module=<module_name> [--sample=name[:emoji] ...] [--dry-run]`. Use this to automate PR #1993-style per-company lookup seed additions (updates inserts only).

#### Full-module browser QA (5 companies, Laragon)

Introduced in [PR #1718](https://github.com/pirica/it-management/pull/1718). Runner FK/delete improvements: [PR #1722](https://github.com/pirica/it-management/pull/1722). Add-step / bulk-order / error-log scope: [PR #1742](https://github.com/pirica/it-management/pull/1742). Bulk skip notes: [PR #1740](https://github.com/pirica/it-management/pull/1740). Sample-data FK parents + seed-only id remap: [PR #1744](https://github.com/pirica/it-management/pull/1744). Use when asked to verify **all modules** across the five seeded companies (TechCorp Global … Enterprise IT).

| Script | Role |
|--------|------|
| `scripts/module_browser_qa_runner.php` | **Browser + CLI:** HTTP session runner — login (`Admin`/`Admin`), company scope, per-module **`mysql`** preflight (`database.sql` INSERT count), **`error_log`** scope, FK-aware clear, sample data, **`add`** (random rows capped by unique scope), **`bulk_delete`** after `add` when rows ≥ `records_per_page`, then search/sort/CRUD/export/**`clear_table`** (before second **`clear`**)/import/`single_delete`/end sample restore + **`error_log`** check. Early module/company preflight; auto-detected Base URL on Laragon (HTTPS→HTTP on localhost); structured **`import_db`** JSON parsing; stale AJAX progress cleanup; optional browser-only **UI click smoke** (one module + one company) appending `bulk_cancel_click`, `pagination_click`, `export_xlsx_click`, and `import_excel_click`. Browser **Run QA** silently runs `module_clean_tests_qa_runner.php` at start and end. Writes timestamped `qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json` and matching **`.xlsx`** each run. Form + **Run QA** (AJAX poll + **Stop**); do not use bare `?run=1` without `ajax=1`. |
| `scripts/module_browser_qa_build_report.php` | **Browser + CLI:** Builds markdown from a runner JSON (pick by date): summary, tier reference, configured step exceptions, **Results by module**, failure categories, **Failures only** and **Skip** quick indexes, preview in browser. Re-Run links preserve **UI click smoke** when set. Writes `qa-reports/module-browser-qa.md` (overwritten each build). |

**Scripts that write sample/test data (DB mutation):**

* `scripts/module_browser_qa_runner.php` (sample seed, random add rows, import round-trip rows).
* `scripts/employees_delete_clear_table_test.php` (creates temporary tenant + employee/access rows).
* `scripts/equipment_delete_clear_table_test.php` (creates temporary tenant + equipment/switch rows).
* `scripts/floor_plans_folder_move_test.php` (creates temporary folder hierarchy rows).
* `scripts/idfs_sync_human_test.php` (creates temporary equipment/switch/idf rows for end-to-end sync checks).
* `scripts/tickets_related_asset_equipment_delete_test.php` (seeds sample ticket rows from `database.sql`).

**Script that dumps seed SQL (no DB writes):**

* `scripts/export_floor_plan_folders_seed.php` prints `INSERT` statements to stdout for pasting into `database.sql`.

**Commands (repository root, Laragon — PowerShell):**

```powershell
cd C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_build_report.php
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --pilot-only
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=expenses --company=4
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=departments --company=1
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=cable_colors --company=1
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=expenses --company=1 --ui-click-smoke
```

**cmd.exe** (no `&`): use backslashes — `"C:\...\php.exe" scripts\module_browser_qa_runner.php [options]`.

Linux/macOS/CI (when `php` is on PATH): `php scripts/module_browser_qa_runner.php [options]`.

**Browser (Laragon):** `http://localhost/it-management/scripts/module_browser_qa_runner.php` + `module_browser_qa_build_report.php` — form → **Run QA** / **Stop** (AJAX poll). Catalog: `scripts/scripts.php`.

**Runner browser form (defaults):**

| Field | Control | Default |
|--------|---------|---------|
| **Module** | Select | **ALL (all modules)** — every `modules/<slug>/` with `index.php`; or one slug (e.g. `expenses`) |
| **Or module slug (manual)** | Text | Empty — when filled, overrides the Module select for this run (`module_manual` → `module` query param) |
| **Company** | Select | **Default company `1` (TechCorp Global)**; **ALL (companies 1–5)** still available in the dropdown |
| **Pilot only** | Checkbox | Off — when checked, runs **`expenses`** only (all selected companies) |
| **UI click smoke** | Checkbox | Off — when checked with **one module + one company**, appends real click steps after the HTTP run |

CLI: omit `--module` / `--company` or use `--module=all` / `--company=all` for all modules / all tenants. Browser form defaults to company **1** unless the user selects **ALL**. CLI `--ui-click-smoke` exits with instructions — use the browser form for real clicks.

**Browser live progress (AJAX):** Click **Run QA** on the form (not bare `?run=1`). JavaScript polls `?ajax=progress&run_id=…` every 400ms while the run request executes with `?run=1&ajax=1&run_id=…`. Progress is written to `qa-reports/.mbqa-progress-{run_id}.json` on each step (`Running QA… co {id} — {module} - {step}`). **Stop** sets a cancel flag (`?ajax=cancel`) and aborts the fetch; the runner exits between companies/modules. CLI unchanged.

**Bare `?run=1` (with or without `stream=1`, without `ajax=1`):** the runner shows an HTML resume page (not a run): `run=1` alone does not poll progress; `stream=1` is legacy NDJSON (often buffered on Laragon). Use the form so the URL includes `ajax=1` and `run_id`.

**Markdown report (`module_browser_qa_build_report.php`):** reads a timestamped runner JSON under `qa-reports/` (browser form: pick date; CLI: `--date=YYYY-MM-DD` or latest) and writes **`qa-reports/module-browser-qa.md`** (overwritten each build). It also regenerates the matching timestamped **`.xlsx`** for that run via `mbqar_build_runner_xlsx()` in `scripts/lib/mbqa_build_report_lib.php` (same basename as the JSON). After `php scripts/module_browser_qa_build_report.php`, the `.md` includes:

1. **Summary** — pass/fail counts  
2. **Skipped steps** — table of `module_step_exceptions` (module, step slug, plain-language label, reason)  
3. **Failure summary (by step)** — fail count, **Typical cause** (all Tier A steps), and **This run** (parsed from the first matching failure note, e.g. `position_no` out of range for `add`)  
4. **Preflight (company switch)** — company id/name, **OK** / **Failed**, short notes  
5. **Results by module** — per-step tables with slug, label, **OK**/**Failed**, notes  
6. **Failures only (quick index)** — compact table for triage (`Module | Co | Step | Label | Notes`)  
7. **Skip (quick index)** — same columns for **Pass** steps whose notes start with `Skip` or `N/A` (see `mbqa_step_note_is_skip_quick_index()` in the runner; truncated at 500 rows; failures index truncated at 200)

**Environment:** `http://localhost/it-management/` with Apache + MySQL (`itmanagement`). The runner uses the same CSRF/login/company session as the browser.

**QA runner tier reference** (canonical lists in `scripts/lib/mbqa_runner_tiers.php`; copied into markdown reports):

| Runner variable | Modules |
|---|---|
| `$bespokeSmoke` (Tier D) | `budget_report`, `expiring`, `rack_planner`, `floor_plans`, `companies` |
| `$skipClear` | `companies`, `users` |

Tier D modules run index navigation smoke only (`list`, `search`, `sort`); other steps are Pass with notes `N/A smoke`, `Skip (bespoke smoke)`, or `N/A`. **`$skipClear`:** tenant FK-aware clear is never run on these tables (shared auth). Tier D also skips start-of-module clear with note `Skip (bespoke smoke)`.

**Tier A step exceptions:** edit `mbqa_runner_module_step_exceptions()` in **`scripts/module_browser_qa_runner.php`** (module slug → step → N/A note). Mapped steps are **not executed**; all other Tier A steps still run. Examples: `user_companies` skips `create`, `add`, `import_db`; `idf_positions` skips both `sample_data` steps with note `N/A (HTTP sample seed failed or empty)`; `patches_updates` skips both `sample_data` steps with note `No sample rows found in database.sql for this module.`; `audit_logs` skips read-only / delete-disabled steps (see runner map).

**Checklist per standard module (Tier A, including Protection Zone folders)** — step order (runner slug = table name unless a step exception applies):

| # | Step | What it checks |
|---|------|----------------|
| 1 | **`mysql`** | Whether `database.sql` defines sample `INSERT` rows for the module table. Parsed from `database.sql` via `itm_parse_database_sql_inserts()` (same tuples as UI sample seed). Manual equivalent: `SELECT * FROM \`{table}\`` in phpMyAdmin on a fresh import — **0 row(s) (empty)** e.g. `ip_addresses`, or **N row(s)** e.g. `departments`. Informational **Pass**; note records the count. Fails only if `database.sql` is missing/unreadable. Tier C/D report `N/A`. |
| 2 | **`error_log`** | Start scope: rename `error_log.txt` to next `error_log-N.txt` when present; else record byte offset (only *new* lines count for this module). |
| 3 | **`list`** | Index HTTP 200, no fatal; Tier A also verifies bulk/pagination gates vs row count. |
| 4 | **`ui_check`** | When an Actions column is present on the index HTML, verifies **`class="itm-actions-cell"`** + **`data-itm-actions-origin="1"`** on the Actions header and at least one body cell when real data rows render (`js/ui-layout.js` / `table_actions_position`). Single-cell colspan empty-state rows (`No records found`, etc.) are ignored. |
| 5 | **`clear`** | FK-aware start-of-module tenant wipe (`companies` / `users` skipped). |
| 6 | **`sample_data`** | HTTP sample seed; FK parents first; DB fallback via `itm_seed_table_from_database_sql()` when anchor ids differ. |
| 7 | **`add`** | Insert ~30 random tenant rows when count &lt; `records_per_page` + 1; grow unique-scope parents first. |
| 8 | **`pagination`** | Page 1 **Next** / page 2 **Previous** when rows &gt; `records_per_page`. |
| 9 | **`bulk_cancel`** | Bulk form + **Cancel** contract (`js/bulk-delete-selection.js`). |
| 10 | **`bulk_delete`** | POST `delete.php` with up to 3 `ids[]` when rows ≥ `records_per_page` (N/A note when skipped). |
| 11 | **`search`** | Search on index. |
| 12 | **`sort`** | Sort links on index. |
| 13 | **`create`** | Create form. |
| 14 | **`view`** | View record. |
| 15 | **`edit`** | Edit form. |
| 16 | **`list_all`** | List-all page. |
| 17 | **`export_pdf`** | Export PDF control in list HTML. |
| 18 | **`export_xlsx`** | Export Excel as OOXML `.xlsx` via `table-tools.js` + `xlsx.full.min.js` (parsed list table; row count ≠ import count). |
| 19 | **`clear_table`** | POST **Clear Table** when rows ≥ `records_per_page` and bulk UI is visible (same gate as `bulk_delete`; runs while export rows are still present). |
| 20 | **`clear`** | Second FK-aware tenant wipe (after export / optional `clear_table`; before import). |
| 21 | **`import_db`** | One insertable row smoke test (`inserted=1` is pass). |
| 22 | **`single_delete`** | Delete POST with FK retry. |
| 23 | **`sample_data`** | End restore on empty table (HTTP). |
| 24 | **`error_log`** | End check: 0 new errors since module scope. |

**`mysql` verification (file or SQL):** prefer reading `database.sql` (runner step 1). To spot-check in MySQL: `SELECT COUNT(*) FROM \`{table}\`` on a database loaded from `database.sql` — expect the same N as the runner note (global insert count, not per-tenant). Empty modules (`patches_updates`, `ip_addresses`, …) drive **`sample_data`** N/A notes such as `No sample rows found in database.sql for this module.`

**Tier A `add` / bulk UI (runner vs browser):**

| Step | Runner (`module_browser_qa_runner.php`) | Manual UI (`js/bulk-delete-selection.js` in `includes/header.php`) |
|------|----------------------------------------|---------------------------------------------------------------------|
| **`add`** | `mbqa_ensure_bulk_sample_rows()` — random inserts until tenant count ≥ `records_per_page` when schema/unique keys allow | N/A (DB-only in QA) |
| **`bulk_cancel`** | Verifies index HTML: `bulk-delete-form`, `bulk-delete-selection.js`, `bulk_action`, **Select to Delete**; static or JS-injected **`data-itm-bulk-cancel="1"`** `type="button"` | First **Select to Delete** → checkboxes + **Delete Selected** + visible **Cancel**; **Cancel** exits without POST |
| **`bulk_delete`** | POST `modules/<slug>/delete.php` with `bulk_action=bulk_delete` and up to 3 `ids[]` (skips two-step UI) | Second click **Delete Selected** submits selected `ids[]` |

**FK-aware clear / delete (PR #1722):**

* **Tenant clear** walks inbound FKs (`information_schema`), deletes child rows for the active `company_id`, then clears the module table. MySQL **1451** retries parse the **child table** from the error text (`` `schema`.`child_table` ``), not the schema name.
* **`single_delete`** POSTs `delete.php`; on “in use by: `employee_positions` (1)” it clears parsed blocker tables (or `itm_find_record_usage`) and retries — **including Protection Zone tables** when required to unblock the delete.
* **Never auto-clear** during FK prep or delete retry: **`companies`** and **`users`** only (shared auth).
* **Skip destructive clear** on `companies` and `users` at the start of each module (same as before).

**Sample / export / import:**

* Sample seed prerequisites are seeded first when configured (e.g. `expenses` → `departments`, `budget_categories`, `cost_centers`, `gl_accounts`; `employee_positions` → `departments`).
* **`error_log` (PR #1742):** If `error_log.txt` cannot be renamed (e.g. Windows file lock), the runner records the current file size and only attributes **new** lines to the active module — avoids false failures from earlier modules. When rotation succeeds, archives are `error_log-1.txt`, `error_log-2.txt`, … under `ROOT_PATH`.
* **Export Excel** is simulated by parsing the list `<table>` HTML (same columns as `table-tools.js`).
* **Import Excel** POSTs **one** derived row to `data-itm-db-import-endpoint` (round-trip smoke, not re-import of every exported line). Uses export headers with insertable values from `database.sql` when UI labels are not IDs. Export row payloads are captured from HTML **before** **`clear_table`** / the second **`clear`**. The runner runs **`clear_table`** (when the bulk gate passes) then **second `clear`** after **`export_xlsx`** so import runs on an empty table. **`expenses`:** import picks a **free** `cost_center_id` for the tenant (`uq_expenses_company_scope`); do not expect `inserted` to match export row count.

**Tiers (do not treat all failures alike):**

* **Tier A** — standard flattened CRUD (`modules/<slug>/index.php`), **including Protection Zone** modules (full checklist; module *code* in Protection Zone is still edit-only per AGENTS unless requested).
* **Tier C** — `is_*` façades (including `is_switch`): routing smoke on `list` / `search` / `sort`; other steps **N/A routing** in `mbqa_runner_module_step_exceptions()`.
* **Tier D** — `$bespokeSmoke` modules (`budget_report`, `expiring`, `rack_planner`, `floor_plans`, `companies`): navigation smoke only.

**UI click smoke:** browser form only — enable **UI click smoke**, select **one module** and **one company**, then **Run QA**. After the HTTP run finishes, JavaScript loads the module index in a hidden iframe and appends click-evidence steps via `ajax=ui_click_evidence`. CLI `--ui-click-smoke` is a guard that exits with instructions to use the form.

**Cursor browser:** Use IDE browser for the **Expenses pilot** (all five companies) and spot-checks; use the CLI runner for full ~101×5 coverage. Latest results: **`qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json`**, matching **`.xlsx`**, and **`module-browser-qa.md`** (commit when publishing QA results).

**Caveats:** Run lookup parents before children (see `$lookupWave` in the runner). Sort-step failures often mean the visible default column is not `id`; confirm via column header links. Modules without `data-itm-db-import-endpoint` report `import_db` as N/A.

#### 5. Pre-merge verification (scripts)

When adding or changing anything under `scripts/`:

1. Confirm a row exists in **`scripts/scripts.php`** (what / how / access).
2. Open the script in the **browser** (if applicable) — **← Scripts index** visible; module names use `../modules/…`; table names link only when a matching module folder exists; no phpMyAdmin links outside `scripts/scripts.php`.
3. Run **`php -l scripts/<changed>.php`** on touched PHP files.
4. Run the script’s CLI command once when behavior is non-trivial.

---

## 🏗 Coding Standards

### 1. Module Structure
Each module must maintain a flat structure with these specific files:
`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, and `list_all.php`.

> [!IMPORTANT]
> **Do not create Master Templates:** Do not attempt to abstract CRUD into a single master template. Each module must remain independent.

### 2. Database & Schema Rules
* **Schema Updates:** If a field/table is deleted or a header renamed, update `database.sql`.
* **Company Scoping:**
    * **Hide** `company_id` from all UI views.
    * Add safe inline FK creation logic to create referenced rows automatically.
    * Scope all queries and inserts by `company_id`.
* **Audit Logging:** The system sets MySQL session variables (`@app_user_id`) in `config.php`. Do not overwrite these.
* **Standard Fields:** Every new table in `database.sql` must include:
    * `company_id` INT NOT NULL
    * `active` TINYINT DEFAULT '1'
    * `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    * `updated_at` TIMESTAMP DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP

### 3. Protection Zone (STRICT: No Auto-Changes)
Do **not** modify logic or structure unless explicitly requested:
`/modules/equipment/`, `/modules/idfs/`, `/modules/idf_links/`, `/modules/idf_positions/`, `/modules/idf_ports/`, `/modules/audit_logs/`, `/modules/employees/, /modules/contacts/`, `/modules/settings/`, `/modules/user_companies/`, `modules/employee_system_access/`, `modules/cable_colors/`, `ui_configuration`.

### 4. Dynamic UI Configuration (Settings)
Modules must read/validate settings via `itm_get_ui_configuration()`:
* **Button Positions:** Render refresh/add controls based on `new_button_position`.
* **Table Actions:** Add **`class="itm-actions-cell"`** and **`data-itm-actions-origin="1"`** to Actions headers and body cells so the global layout engine can map `table_actions_position` (`js/ui-layout.js`). Module browser QA **`ui_check`** step fails when an Actions column renders without both markers on the header (and on body cells when data rows exist).
* **DB Import Endpoint (Index Tables):** Add `data-itm-db-import-endpoint="index.php"` to every module index table so `📥Import Excel` can use the save-to-database flow.
* **Global Behaviors:** Respect system toggles for `enable_all_error_reporting`, `enable_audit_logs`, and `records_per_page`.

### 5. Standard Feature Set
Every module (excluding the Protection Zone) must implement:
* **Hide** `company_id` from all UI views.
* **Bulk Actions:** "Select to Delete" and "Clear Table" (visible when row count >= `records_per_page`). Use shared **`js/bulk-delete-selection.js`** (loaded from `includes/header.php`) — see **Bulk delete toolbar and Cancel button** below.
* **Search:** Comprehensive search across all visible fields — see **List/search visible columns** below.
* **Order:** Standardized sort fields ASC DESC - '▲' : '▼'.
* **Tools:** `📗Export Excel`, `📄Export PDF`, and `📥Import Excel` (linked via `js/table-tools.js`).
    * **Disabling Exports:** Automatically generated export buttons can be hidden by adding `data-itm-no-export-excel="1"` or `data-itm-no-export-pdf="1"` to the `<table>` element or its parent `.card`.
* **Navigation:** Standardized server-side pagination based on `records_per_page`. List **Previous** / **Next** controls use link text `Previous` and `Next` (preserve `search`, `sort`, `dir`, and `page` query params). **Required `title` attributes:** `title="◀️ Previous"` and `title="▶️ Next"` on pagination anchors (not `🔎 Search`). Pagination URLs include `search=`; `includes/header.php` auto-tooltips must match **visible link text** for Next/Previous and must not treat `search=` in `href` as a Search action. **QA (`pagination` step, after `add`):** when rows > `records_per_page`, verify server HTML on page 1 includes **Next** (`btn-sm`, `page=2`, `title="▶️ Next"`), then page 2 includes **Previous** (`btn-sm`, `page=1`, `title="◀️ Previous"`) — `index.php?search=&sort=id&dir=DESC&page=1` then `page=2`.
* **Error Reporting:** Standardized server-side `enable_all_error_reporting` value from Settings.
* **Enable Audit Log:** `enable_audit_logs` value from Settings.
* **Audit Trail Coverage:** Mandatory INSERT/UPDATE/DELETE logging to `audit_logs` if enabled so changes are traceable in the audit center.

#### Rack Planner price source sync (mandatory)

When Rack Planner stores a priced device with code `catalog:<id>`, `equipment:<id>`, or `idf_unlinked:<token>`, price changes must persist to source tables as part of save/autosave:

1. `catalog:<id>` -> update `catalogs.price`.
2. `equipment:<id>` -> update `equipment.purchase_cost`.
3. `idf_unlinked:<token>` -> update `idf_positions.price` for matching token-style `equipment_id` (`^[0-9]{4}-[0-9]{4}$`) in the active company.

Do not keep price edits only inside `rack_planner.layout_json`; source tables must remain aligned.

#### Explorer module (mandatory)

The explorer module (`modules/explorer/`) provides a secure, multi-tenant file system.

1. **Storage:** Anchored at `/files/{company_id}/` and subdivided into `Common/` (all company users), `Departments/{dept_id}/` (department members only), and `Private/{username}_{user_id}/` (owner only).
2. **Access Control:**
    - Segment-boundary checks must be used for path validation (e.g. check for `Private/{owner}/` or exact `Private/{owner}`).
    - Users can only access `Common`, their assigned `Departments/{dept_id}`, and their own `Private/{username}_{user_id}`.
    - Creation or upload of items is blocked directly in `Home` (root), `Private` root, and `Departments` root.
3. **Protected Folders:** Top-level system folders (`Common`, `Departments`, `Private`) and the user's primary private folder (`Private/{username}_{user_id}`) cannot be renamed, moved, or deleted.
4. **Localisation:** Use UK English (en-GB) for all UI labels (e.g., 'Favourites', 'Trash').

#### Org Chart and Hierarchy (mandatory)

The org chart module (`modules/org_chart/`) provides a visual representation of the company hierarchy.

1. **Data Source:** Reporting lines are stored in the `employees.reports_to` column (self-referencing FK).
2. **Cycle Detection:** Hierarchy updates (drag-and-drop) must perform recursive cycle detection to prevent circular reporting loops.
3. **Layout:** The module uses a dynamic tree layout algorithm to position nodes automatically based on reporting lines.
4. **Persistence:** Changes to the hierarchy via drag-and-drop are saved immediately via AJAX to the `employees` table.

#### Visitors Access Log (mandatory)

The visitors access log module (`modules/visitors_access_log/`) provides a way to track manual entry logs of visitors.

1. **Quick Add:** The index view features a persistent first row for quick logging of new visitors.
2. **Security & Immutability:** Historical records (not created today) are locked for editing and deletion to maintain audit integrity.
3. **Fallbacks:** The `val_is_today` helper must fall back to `created_at` if `date_time_in` is not provided.
4. **Layout:** The module follows the action-wrapper pattern and includes standard sidebar and header components.

#### Employees module import logic (mandatory)

The employees module (`modules/employees/`) provides specialized import logic for handling various corporate data formats.

1. **Header Aliases:** The import must handle industry-specific and export-specific headers such as 'Hilton ID' (maps to `external_id`), 'Position Title' (maps to `employee_position_id`), 'Department Name' (maps to `department_id`), and sorting markers like 'Id▼' (maps to `id`).
2. **ID-based Updates:** If an 'id' column is present in the import data, the system must attempt to update the existing record rather than creating a duplicate.
3. **Automated Entity Resolution:**
    - **Departments:** If a department name is provided but not found, it must be automatically created for the company.
    - **Positions:** If a position title is provided but not found, it must be automatically created and linked to the resolved department.
4. **Email Classification:** The system must automatically classify emails. Known personal domains (e.g., gmail.com, yahoo.com) route to `personal_email`; others route to `work_email`.
5. **Boolean Normalization:** Support for common export markers is mandatory. Convert '✅' or 'Active' to `1` and '❌' to `0` for boolean fields like `on_contacts` and `on_orgchart`.

#### Backup Tape Log (mandatory)

The backup tape log module (`modules/backup_tape_log/`) provides a monthly grid to track server backups.

1. **Monthly Grid:** The index view renders one row for every day of the selected month/year/server combination.
2. **Auto-population:** The `log_date` and `tape_to_be_used` (day name) are automatically derived for each row. Sundays must be highlighted in yellow.
3. **Immutability:** Records not from today are locked for editing and deletion.
4. **Restricted Fields:** The `tape_used_for_restore` and `ism_review` fields are only editable by Admin users or staff assigned to the IT department.
5. **Exports:** XLSX and PDF exports must include the custom header (Year, Month, Company, Server, Unit No) and follow the requested grid layout.

#### Calendar, Alerts and Events integration (mandatory)

The calendar module (`modules/calendar/`) provides a centralized view of time-sensitive data.

1. **Integration (Sync):** The calendar must automatically pull and display:
    - Alerts from the `alerts` module. (only with `end_datetime`)
    - Events from the `events` module.
    - Tickets with a `due_date` (displayed as tasks).
    - Equipment with a `certificate_expiry`.
    - Equipment with a `warranty_expiry`.
2. **Standard CRUD:** The `events` and `event_categories` modules must follow the standard CRUD structure and multi-tenancy rules.
3. **UI:** The calendar grid must follow a Monday to Sunday layout.


#### Alerts module (mandatory)

The Alerts module (`modules/alerts/`) handles global alerts when `assigned_to_user_id` is NULL and private alerts visible only to the assigned user and the creator.

1. **Visibility Logic:**
    - **Global Alerts:** `assigned_to_user_id IS NULL`. These are visible to all users within the same company.
    - **Private Alerts:** `assigned_to_user_id = $logged_user_id`. These are visible only to the assigned user and the creator.
    - `$assigned_to_user_id = $logged_user_id AND $created_by_user_id = $logged_user_id`
2. **ICS Import:** Supports importing events from ICS files.
3. **Multi-tenancy:** Strictly scoped by `company_id`.

#### Bookmarks module (mandatory)

The Bookmarks module provides a hierarchical management system for links, featuring:
- **Privacy Scoping:** Data is filtered by `user_id` for private bookmarks and `company_id` for shared ones.
- **Dual-Pane UI:** A left sidebar with an emoji-enhanced folder tree (📁/📂) and a main list view.
- **Drag-and-Drop:** Folders can be reordered or reparented via drag-and-drop interactions.
- **Import/Export:** Supports standard browser HTML bookmark files, CSV, and XLSX exports.
- **Permissions:** Shared bookmarks are read-only for regular users, while admins and creators retain full CRUD access.

#### Bulk delete toolbar and Cancel button (mandatory)

Standard index markup (inside the list card, above the search row). `department-bulk-form` is the legacy id for `modules/departments/` only; all other modules use `bulk-delete-form`.

```html
<div class="card" style="margin-bottom:16px;">
    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
        <input type="hidden" name="csrf_token" value="...">
        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger"
            onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
    </form>
</div>
```

| Control | Server HTML | Runtime (`js/bulk-delete-selection.js`) |
|--------|---------------|------------------------------------------|
| **Select to Delete** | `button[name="bulk_action"][value="bulk_delete"]` — initial label **Select to Delete** (or module-specific text; JS reads it on load) | First click: `preventDefault`, show row `ids[]` checkboxes + header select-all, relabel toggle to **Delete Selected**, show **Cancel** |
| **Cancel** | Optional in PHP; if omitted, JS injects `button[type="button"][data-itm-bulk-cancel="1"]` immediately after the bulk_delete toggle | `type="button"` only — must **not** submit the form. Click runs `exitSelectionMode()`: hides checkboxes, restores **Select to Delete** label, hides Cancel, clears checks — **no POST** |
| **Delete Selected** | Same submit button as Select to Delete (label changes in JS) | Second click on toggle: confirm + POST `delete.php` with selected `ids[]` |
| **Clear Table** | Separate `bulk_action=clear_table` submit (with confirm) | Unaffected by selection mode |

**Hard rules:**

1. **Shared script only** — include `bulk-delete-selection.js` via `includes/header.php`. Do **not** copy inline `let selectionMode = false` handlers into module `index.php` files.
2. **Cancel never submits** — `data-itm-bulk-cancel="1"` buttons must be `type="button"`, not `type="submit"`.
3. **Row checkboxes** — `input[name="ids[]"]` with `form="bulk-delete-form"` (or matching form id); hidden until selection mode.
4. **Bound once** — JS sets `data-itm-bulk-delete-bound="1"` on the form after attach (safe to reload DOMContentLoaded).

**QA (`bulk_cancel` step, after `add`):** when bulk UI is visible, index HTML must include `bulk-delete-form`, `bulk-delete-selection.js`, `bulk_action`, and **Select to Delete** / `bulk_delete`; pass note lists what rendered in HTML plus shared JS contract. N/A when row count &lt; `records_per_page` (no bulk card).

#### Bulk UI row gate (`$perPage`) (mandatory)

After the tenant row count for the index list, resolve page size from UI config and gate bulk toolbar visibility:

```php
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$showBulkActions = ($totalRows >= $perPage);
```

| Check | Rule |
|--------|------|
| **Bulk toolbar** (`Select to Delete`, `Clear Table`, row `ids[]` checkboxes) | `$totalRows >= $perPage` |
| **Pagination footer** (`Previous` / `Next`) | `$totalRows > $perPage` |
| **Rejected (inverted)** | `$perPage >= $totalRows` or `if ($perPage >= $totalRows)` before bulk markup — hides bulk UI when QA expects `bulk_delete` / `clear_table` |

`scripts/check_ui_configuration_coverage.php` fails inverted gates. Module browser QA skips bulk steps with `N/A (N rows < perPage 25 …)` when the gate is correct but the tenant has fewer rows than `records_per_page`.

**Reference parents with inbound FKs:** when `inventory_items` (or other children) reference the module table without `ON DELETE CASCADE`, detach or clear child FK columns for the active `company_id` **before** `DELETE` on the parent (`inventory_categories`: `UPDATE inventory_items SET category_id = NULL …` then delete categories). Otherwise `bulk_delete`, `clear_table`, and `single_delete` can return HTTP 200 while rows remain and QA reports “still listed”.

#### List/search visible columns (`$uiColumns` / `$displayFieldColumns`) (mandatory)

Flattened CRUD `index.php` files build **`$uiColumns`** (visible list fields, often hiding `company_id` via `$hideCompanyIdTables`). List tables and sort use `$uiColumns`. **Search** must query the **same visible column set**. A common template mistake copied `foreach ($displayFieldColumns as $col)` in the search block without defining `$displayFieldColumns`, which logs `Undefined variable: displayFieldColumns` and `Invalid argument supplied for foreach()` when `?search=` is set (module browser QA **search** step exercises this on many modules).

**Mandatory contract:**

1. After **`$uiColumns`** is finalized (including any extra filters, e.g. `floor_plans` `list_all` column subset or `employee_onboarding_requests` list columns), assign **before** `$modulePath = dirname($_SERVER['PHP_SELF']);`:

```php
// Why: Search and list share visible columns; alias matches role/ui_configuration modules.
$displayFieldColumns = $uiColumns;
```

2. Modules that use **`$visibleFieldColumns`** for the list (e.g. `cable_colors`, `switch_ports`): `$displayFieldColumns = $visibleFieldColumns;` after that array is final.

3. Any **`foreach ($displayFieldColumns as $col)`** in the search SQL block requires the assignment above in the same request. Do not reference `$displayFieldColumns` without defining it.

4. List/view HTML may keep `foreach ($uiColumns as $col)` (or `$visibleFieldColumns`); search may use `$displayFieldColumns` or `$uiColumns` only **after** the alias line exists.

**Audit / repair** (catalog: `scripts/scripts.php`):

| Script | When |
|--------|------|
| `php scripts/check_display_field_columns_search.php` | After changing flattened `index.php` search or list column variables; exit `1` if any index uses `$displayFieldColumns` without assignment |
| `php scripts/apply_display_field_columns_search_alias.php` | CLI-only maintenance to add the alias on modules missing it (idempotent; re-run when scaffolding new flattened modules) |

Not part of smoke — run manually when CRUD template or search logic changes. Bulk fix: [PR #1796](https://github.com/pirica/it-management/pull/1796).

### 6. Empty-State Sample Data Process
* **UI:** Add "Add sample data" button at the bottom of `index.php` if the result set is empty for the active company.
* **Handler:** Implement a `POST` handler for `add_sample_data` in `index.php` that:
    * validates CSRF (`itm_require_post_csrf()`),
    * confirms there is an active `company_id`,
    * re-checks the table is empty for that `company_id` before inserting.
* **Source:** Seed rows must match `INSERT INTO` entries in `database.sql` for that module table.
* **Tenant Safety:** Always write seeded rows with active `company_id`; never expose/edit `company_id` in UI.

### 7. Module Consistency Guardrail (Mandatory)
When a module uses duplicated procedural entry files (`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`):
* **Apply critical behavior fixes consistently** across all module entry files when they share the same helper blocks (rendering, CSRF validation, FK option loading).
* **Incomplete implementation is not acceptable:** if a fix is made in one duplicated entry file, you must recheck and apply it to all matching duplicated files before finishing.
* **Mandatory recheck checklist:** verify behavior consistency in `index.php`, `view.php`, `edit.php`, `create.php`, and `list_all.php` (plus `delete.php` when applicable) for the changed module before commit.
* **Boolean detection consistency:** If checkbox detection logic is updated (example: `active` with `tinyint` variant handling), propagate the same update to every duplicated entry file that shares create/edit rendering or POST normalization paths.
* **For display renderer updates** (for example badges/swatches/label mapping), propagate the same renderer/helper logic to both `index.php` and `view.php` before commit.
* **Verify FK label rendering in both list and detail/edit flows** (no raw FK IDs when a related label exists), including company-scoped fallback behavior where seeded reference rows may be missing for a tenant.
* **FK label guardrail (hard fail):** if a module list/view screen shows raw FK IDs such as `equipment_id=5` or `level_id=23` while a related label row exists, the task is **not complete**. You must fix label rendering and tenant-safe fallback lookup before commit.
* **Switch Status FK + color fallback guardrail (mandatory for `modules/switch_status/`):**
    * Preserve persisted FK selections when tenant-scoped option queries do not return the saved row (do not let edit forms fall back to `-- Select --` for existing values).
    * For `color_id`, keep swatch rendering resilient by resolving `hex_color` with tenant-scoped lookup first (`id` + `company_id`) and then global-by-`id` fallback for legacy/shared rows.
    * Keep duplicated entry files aligned (`index.php`, `edit.php`, `view.php`) so list/detail/edit flows all use the same FK fallback and color preview behavior.
* **Ensure FK dropdowns preserve persisted selections:** if a saved FK value is not returned by the current company-scoped options query, append/load that saved value so edit forms do not fall back to `-- Select --`.
* **Mandatory FK recheck before commit (all changed modules):**
  * Open `index.php` and `view.php` and confirm FK columns render labels (not numeric IDs).
  * Open `edit.php` and confirm persisted FK values remain selected even when company-scoped options are incomplete.
  * Confirm fallback lookup is tenant-safe (company scoped first, then id-only fallback only for preserving legacy/shared references).
* **Mandatory column + SQL relation audit (hard fail):**
  * For mandatory review requests, audit **all columns and SQL relations** for each requested module folder before code changes are finalized.
  * Validate visible-column rendering in `index.php`, `view.php`, and `list_all.php` against `database.sql`/`information_schema` relationships.
  * Replace raw foreign keys with meaningful related display values whenever possible (for example hostname, status, color name, VLAN name, device/position labels).
  * Include both declared FK constraints and relation-like `*_id` columns that may be stored without an explicit FK constraint.
  * Keep tenant-safe lookup order: `company_id` scoped lookup first, then id-only fallback only for legacy/shared references when scoped rows are missing.
  * If a related human-readable label exists but any visible screen still shows raw FK IDs, the task is not complete.
* **Created-by UX guardrail (hard fail):**
  * For fields such as `created_by`, `updated_by`, `approved_by`, and `*_by_user_id`, list/detail screens must never show raw numeric IDs when a user row exists.
  * In `create.php`/`edit.php`, these fields must render as user dropdowns (human-readable labels), not free-text numeric inputs.
  * User labels must prefer `first_name + last_name`; use `username` only as fallback when full name is empty.
  * If a persisted user ID is missing from company-scoped options, append/load the saved value so edit forms do not reset to `-- Select --`.
* **Testing/reporting guardrail (mandatory):**
  * Do not claim “No tests run” when checks were executed.
  * **Windows Laragon:** run and document PHP tests with the **full PHP binary path** and correct shell syntax — **PowerShell:** `& "C:\...\php.exe" scripts/...`; **cmd:** `"C:\...\php.exe" scripts\...` (see **Setup & Debugging → PHP CLI tests — full binary path (mandatory)**).
  * Minimum required checks for CRUD changes: `php -l` on touched PHP files and `php scripts/check_sql_injection_coverage.php` (use full PHP path on Windows Laragon).
  * When changing flattened `index.php` list/search column variables: `php scripts/check_display_field_columns_search.php` (see **List/search visible columns** above).
  * Optional broad QA (all modules × five companies): `php scripts/module_browser_qa_runner.php` then `php scripts/module_browser_qa_build_report.php` — list exact pass/fail counts in the PR when run (see **Full-module browser QA** under `scripts/`).
  * After employees/equipment `clear_table` changes: `php scripts/check_employees_clear_table_transaction.php`, `php scripts/check_equipment_clear_table_delete.php`; optional DB regression per `scripts/scripts.php` (`employees_delete_clear_table_test.php`, `equipment_delete_clear_table_test.php`). Run `php scripts/cleanup_equipment_test_module_artifacts.php` when equipment regression tests touched the database.
  * PR descriptions must list the exact commands that were run and their outcomes.
* **Branching and PRs:** Follow **NEW PR always** under **Change Hygiene → PR review (mandatory)** (fresh branch + new `gh pr create` per deliverable; do not reuse merged PRs for new scope).
* **IDF synchronization guardrail (mandatory for `modules/idfs/view.php`, `modules/equipment/`, `modules/switch_ports/`, and `modules/idfs/device.php`):**
  * All **Create, Edit, Update, Delete, Copy, and Move** operations must keep the following tables fully synchronized at all times: `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links`.
  * **Hard-fail policy:** partial cross-table updates are not allowed. When a workflow touches multiple IDF-related tables, use transaction boundaries and rollback on any synchronization failure.
  * **Delete/overwrite/move safety:** cleanup/update dependent `idf_links` and `idf_ports` rows before deleting or replacing `idf_positions`, and always keep `equipment.idf_id` and `switch_ports.idf_id` aligned with the active IDF link state.
  * `link_create`, `port_update`, `link_delete`, `position_save`, `position_delete`, `position_copy`, and move/reorder actions must preserve status/color/label/notes parity across linked `idf_ports` and mirrored `switch_ports` rows.
  * **Unknown reset rule:** unlink/delete flows must reset synchronized ports to tenant `Unknown` + Gray (`#808080`) defaults where applicable.
  * **Wrapper consistency finding (switch_ports):** if `create.php`, `edit.php`, `view.php`, `delete.php`, or `list_all.php` are wrappers that route to `index.php`, `index.php` must not overwrite wrapper-provided `$crud_action` values.
  * **High-Density Support:** Rack position validation supports up to **250** positions. Batch updates (move/reorder) use temporary offsets (1000) to avoid unique constraint collisions.
  * **Mandatory human-flow testing for every affected workflow:** execute human-flow regression for each changed Create/Edit/Update/Delete/Copy/Move path before PR.
    * Required regression command (from repository root):
      * **Default (Linux, macOS, CI, PATH):** `php scripts/idfs_sync_human_test.php`
      * **Windows Laragon fallback (when `php` is not on PATH):** `cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management` then `"C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\idfs_sync_human_test.php`
    * If any workflow or command run reports `[FAIL]`, the task is not complete.
* **Before commit, smoke-check all three screens at minimum:** list (`index.php`), detail (`view.php`), and edit (`edit.php`) for the changed module.
* **Wrapper action routing guardrail (mandatory):** for modules that use wrapper entry files (`create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) to set `$crud_action` before requiring `index.php`, verify `index.php` does not overwrite wrapper-provided values. Confirm each wrapper still routes to its expected screen/handler before creating a PR.
* **API:**
   * `scripts/api.php` needs to be updated if any changes on the project.
* **Implement the missing JSON import endpoint:**
   * For modules/*/index.php, so 📥Import Excel now handles table-tools.js save-to-database requests instead of falling through to normal page rendering (which caused the generic “Import failed while saving to database.” error).

---

## 🔒 Security Protocol

### SQL Injection (SQLi)
1. **Prepared Statements:** ALWAYS use MySQLi prepared statements for user data.
2. **Identifier Validation:** Use `itm_is_safe_identifier($name)` for table/column names.
3. **Execution:** Use `itm_run_query($conn, $sql)` with error trapping.
4. **Audit:** Run `php scripts/check_sql_injection_coverage.php` after changes.

### CSRF & XSS
* **CSRF:** Use `itm_require_post_csrf()` in handlers. Forms require:
  `<input type="hidden" name="csrf_token" value="<?= itm_get_csrf_token() ?>">`
* **XSS:** Wrap all echoed user-provided strings in `sanitize($data)`.

### Directory Listing Prevention
* Every publicly accessible directory MUST contain an `index.php` or `index.html` file to prevent directory listing (unless listing is disabled via server configuration).

## 🛡️ Safety & Side Effects
* **Risk of Regression (login.php):** Any changes to the login flow (e.g., joining with `user_roles`) must be carefully verified against the schema in `database.sql` to avoid breaking authentication for all users.
* **UI Redundancy:** Modules with custom export layouts should disable the default `table-tools.js` buttons using the `data-itm-no-export-*` attributes.

---

## 💡 Development Patterns

### PHP Best Practices
* **Paths:** Use `ROOT_PATH` with a trailing slash for filesystem operations.
* **Variable Collisions:** Use unique, prefixed variables in `includes/` (e.g., `$itm_sidebar_user`).
* **Commenting:** Follow the **"Why-Focused"** style.
    * *What:* "Looping through array" (Avoid).
    * *Why:* "Human-friendly labels for UI positioning settings stored in the database." (Prioritize).

### UI/UX Requirements
* **Layout:** `.container` > `.main-content` > `.content`.
* **Hide** `company_id` from all UI views.
* **Foreign Keys in UI:** Never display raw FK numeric IDs in list/detail screens when a related label exists. Render human-readable values (e.g., `name`, `title`, `username`) instead.
* **Buttons:** `btn-primary` for main actions; `btn-sm` for table actions.
* **Tables:** Use `.itm-actions-cell` and `.itm-actions-wrap` for action columns.
* **Active field use badges for status (index.php/view.php).
* **Booleans (List View):** Use badges for status (must NOT contain emojis):
    * `<span class="badge badge-success">Active</span>`
    * `<span class="badge badge-danger">Inactive</span>`
* **Booleans (Edit Mode):** Use icons: `1` = ✅, `0` = ❌.
* **Active Checkbox Guardrail (Double-Label Pattern):** In create/edit forms, treat `active` as a checkbox boolean for `tinyint` variants. Every form field must have a top-level `<label>`. For checkboxes, use the `itm-checkbox-control` wrapper with an inner `<span>` that repeats the humanized field name and includes the status indicator:
```html
<div class="form-group">
    <label><?= sanitize(cr_humanize_field('active')) ?></label>
    <label class="itm-checkbox-control">
        <input type="checkbox" name="active" value="1" <?= ($isActive ? 'checked' : '') ?>>
        <span><?= sanitize(cr_humanize_field('active')) ?> <span class="itm-check-indicator" aria-hidden="true"><?= ($isActive ? '✅' : '❌') ?></span></span>
    </label>
</div>
```
* **Strict prohibition:** HTML forms for the `active` database column must use the checkbox pattern above. The use of `<input type="text" name="active" value="1">` or `<input type="text" name="active" value="0">` is strictly forbidden.


* **Dynamic Selects:** Enable quick-add functionality: `<option value="__add_new__">➕</option>`.
* **Color Fields:** Use color picker UI: `<input type="color" name="hex_color" id="cable-hex-color-picker" value="#008000">`.
* **Date Fields:** Show date picker UI.
* **Switch Port Manager icon mapping (mandatory):** For `modules/equipment/index.php` generated switch port tiles, keep one centered icon with centered port number overlay. Map by port type + status (`Unknown` check is case-insensitive): RJ45 `Unknown` → `images/switch_port_icons/rj45_38x31_Unknown.png`, RJ45 non-`Unknown` → `images/switch_port_icons/rj45_38x31.png`, SFP `Unknown` → `images/switch_port_icons/sfp_38x38_Unknown.png`, SFP non-`Unknown` → `images/switch_port_icons/sfp_38x38.png`. When status is saved, refresh the icon immediately without page reload.

---

## 🛠 Setup & Debugging
* **Dev Credentials:** Host `localhost` | user `root` | **password `itmanagement`** | database `itmanagement` — same value in `config/config.php` (`DB_PASS`) and on the MySQL CLI (`-u root -pitmanagement`; the `-p` flag has **no space** before the password).
* **Windows Laragon portable — local paths (Nelson, verified):** When `php` / `mysql` are not on PATH, use these full absolute paths:

| What | Local path |
|------|------------|
| Laragon root | `C:\Users\NelsonSalvador\Downloads\laragon-portable` |
| ITM repository | `C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management` |
| App URL (Apache) | `http://localhost/it-management/` |
| phpMyAdmin (local) | `http://localhost/phpmyadmin/` |
| **PHP 7.4.33 (ITM — use this)** | `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe` |
| MySQL 8.4 CLI | `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |

### PHP CLI tests — full binary path (mandatory — Windows Laragon)

On **Windows Laragon** (Nelson's environment), **always** use the **full absolute path** to the PHP 7.4.33 binary when running PHP tests, audits, and `scripts/*.php` — do **not** rely on bare `php` on PATH.

| Rule | Detail |
|------|--------|
| **Shell** | Nelson's default terminal is **PowerShell** — a quoted path alone is a string, not a command. Prefix with the call operator **`&`**. Use **cmd** syntax only inside `cmd /c` or a `.cmd` session. |
| **Run commands (PowerShell)** | From repo root: `& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/<script>.php [options]` |
| **Run commands (cmd.exe)** | `"C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\<script>.php [options]` |
| **PR test plans / agent replies** | List the **exact full-path command** executed for the shell used (PowerShell with `&`, or cmd) — not shortened `php scripts/...` |
| **Verification example (PowerShell)** | `& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=cable_colors --company=1` |

**PowerShell error:** `Unexpected token 'scripts/module_browser_qa_runner.php'` means the line is missing **`&`** before the quoted `php.exe` path.

On **Linux, macOS, CI, and any host where `php` is on PATH**, bare `php scripts/...` remains acceptable.

  * **CLI example (repo root — PowerShell):**
    ```powershell
    cd C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
    & "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=cable_colors --company=1
    ```
  * **CLI example (repo root — cmd.exe):**
    ```cmd
    cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
    "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\module_browser_qa_runner.php
    ```
    On Windows, run the three `scripts/smoke_test.sh` checks individually with that PHP binary, or use Git Bash: `bash scripts/smoke_test.sh`.
  * **Import `database.sql` (full file — do not strip the first lines):**
    ```cmd
    cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
    "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -pitmanagement --default-character-set=utf8mb4 < database.sql
    ```
    Verify: `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='itmanagement';` → **88**, or `php scripts/verify_database_schema.php` (lists any missing tables). A partial import often stops at table **73** (`user_companies`) — missing block includes `role_hierarchy` … `workstation_ram`, `rack_planner`. Common deploy bugs: stripping the first lines of `database.sql` (removes `DROP DATABASE`), wrong MySQL password, or `re-download-replace_DB.ps1` piping without `-pitmanagement`. Use the updated `laragon-portable\www\re-download.ps1` (full file + table count). Capture stderr (`2> mysql-import.err`) — MySQL may exit 0 while statements failed.
  * **PowerShell piping:** `database.sql` in git is **LF**; `-split "`r`n"` can yield a single “line” and skip the strip branch — still import the **complete** file. Prefer `cmd /c "\"C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe\" -u root -pitmanagement --default-character-set=utf8mb4 < database.sql"` from the repo directory over stdin `Process` piping when imports truncate.
* **Online AI Test Environment:**
  * `https://nelsonsalvador.myddns.me` | Login: `Admin` | Password: `Admin`.
  * `http://nelsonsalvador.myddns.me/phpmyadmin/` | Database: `itmanagement` | Login: `root` | Password: (blank).
  * Note: `https://nelsonsalvador.myddns.me/phpmyadmin/` currently returns upstream TLS/certificate errors; use HTTP for phpMyAdmin checks.
* **Logs:** System errors are piped to `ROOT_PATH . 'error_log.txt'`.
* **Testing:** Browser screenshots are not supported; rely on verbose error logging. For full-module CRUD/button regression across five companies, see **Scripts directory → Full-module browser QA** (`module_browser_qa_runner.php`, [PR #1718](https://github.com/pirica/it-management/pull/1718)).
* **CLI scripts:** Run from the repository root with **PHP 7.4.33** and **MySQLi** enabled.
  * **Linux, macOS, CI, and any host where `php` is on PATH:** `php scripts/<script>.php`
  * **Windows (Laragon) when `php` is not on PATH:** use `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe` from **Windows Laragon portable — local paths** above.

---

## 🎯 Root cause fixes (mandatory — no quick patches)
When a bug or review comment points at a symptom (wrong delete guard, flaky QA step, misleading label), **fix the underlying contract**, not the nearest line that makes the symptom go away.

* **Find the producer first:** identify what creates the bad state (for example `module_browser_qa_runner.php` random row tags, import Excel usernames, FK seed order). Read that code path before patching consumers.
* **One canonical marker:** test-only or disposable rows must use a **shared, documented signature** in one helper (for example `includes/itm_mbqa_test_user.php` for `MBQA-{table}-{company}-{seq}-{hash}`). Runner and modules must call the **same** builder and detector — do not duplicate regex/prefix logic.
* **Do not broaden security bypasses on untrusted data:** usernames, titles, emails, and free-text fields are normal application data. A short prefix match (`mbqa-`, `test-`, `qa-`) is **not** an acceptable root fix for delete guards or permission skips; use the strict runner/import signature or another trusted marker.
* **If the real fix is larger, say so in the PR** (schema flag, dedicated test role, runner cleanup hook) and implement the smallest **correct** contract change — not a permanent loose bypass.

---

## 🧹 Change Hygiene Rules (Diff Quality)
To keep PRs reviewable and avoid noisy churn, follow these rules for every change:
* **No line-ending normalization:** Preserve existing CRLF/LF style per file. Do not rewrite whole files just to change one line.
* **No broad search/replace across modules unless explicitly requested:** Prefer targeted edits to only the files required by the task.
* **Minimize touched lines:** Keep patches surgical and avoid formatting-only edits (spacing, wrapping, reindent) when logic is unchanged.
* **Preserve file encoding and structure:** Do not change charset, BOM behavior, or module layout unless requested. See **Character encoding, locales, and Unicode** — source files stay UTF-8 without BOM; only `qa-reports/*` JSON/MD may get a UTF-8 BOM from `itm_write_utf8_text_file()`.
* **If a change must be bulk-applied, state why in the PR description** and confirm the scope before continuing.

### PR review (mandatory)
* **Reviews and fixes** are done in-repo via Cursor, optional Bugbot, manual IDE review, and the scripts below — same intent as external P1/P2 bot comments.
* **FRESH PR only — never update an existing PR (mandatory — non-negotiable):**
  * **Every separate request, bugfix, follow-up, Codex/Bugbot fix, or AGENTS.md process update** ships on a **fresh branch** and opens a **brand-new pull request** with a **new PR number**. The user default is **never update an existing PR** for new work.
  * **`git push` is not a way to amend PR #N.** Allowed push pattern: **one** `git push -u origin <new-branch>` while publishing a branch that has **no PR yet**, then **`gh pr create`**. Any later change → **new branch** → **new push** → **new PR**.
  * **Do not push more commits to an open PR’s branch** — even for review fixes, CI fixes, or “small follow-ups”. If unsure, use a **fresh branch + new PR**.
  * Treat **“always a NEW PR”** and **“DON’T push to PR #N”** literally: **`gh pr create`** for each deliverable; the prior PR is history — **next change = next PR number**.
  * **Branch naming:** each deliverable gets a **new** `fix/…` or `feat/…` branch from current `origin/master` — do **not** reuse `fix/inventory-items-ui-consolidate` (or any branch that already has PR #1860, #1858, etc.) for additional commits.
  * Package every requested implementation in that **fresh branch** and open that **new PR** when the work is ready—do **not** wait for an explicit “please commit” (unless the user asked to hold commits or the session is exploratory/read-only).
  * When required checks pass, **commit**, **`git push -u origin <new-branch>`**, and **`gh pr create`**. A task is not complete with only unstaged or unpushed local changes.
  * **NEVER commit only locally:** if `git status` shows `ahead 1` (or any “ahead of origin”), the deliverable is **incomplete** until push succeeds and a **new PR URL** (new number) is returned. Forbidden closing lines: “only committed locally”, “branch is ahead of origin by 1”, “push when ready”, “Pushed to …/pull/1860”, “updated the existing PR”.
  * **Do not ask for PR confirmation:** never end a deliverable with prompts like “say so and I will open a PR” or “would you like me to commit?” — **auto-open the fresh PR** when work is ready (same exceptions as **Agent compliance workflow** step 5).
  * **Completion checklist (same turn, before replying done):** `git checkout -b fix/new-unique-name` (from synced `master`) → commit → `git push -u origin HEAD` → **`gh pr create`** → reply with the **new PR URL** (must be a URL the user has not been given before for this deliverable). Do **not** treat `gh pr view` on an old PR as completion.
  * Do not reuse a previously opened **pull request** for a **new** request, even if the files overlap.
  * **Review replies vs code:** posting **`Fixed`** on an old PR thread is allowed; **code** for that fix still lands on a **fresh branch + new PR** unless the user explicitly said to amend the open PR.
  * Preferred status wording example: “I’m now packaging this as a fresh branch/PR (per your **NEW PR always** rule) with the root sync fixes, the human-flow regression test, and the AGENTS guardrail update.”
* **Avoid GitHub “We couldn’t merge this pull request” errors:**
  * **Always branch from current `origin/master`** before new work — never stack new commits on a branch whose PR already merged (GitHub may show merge errors or stale diffs).
  * **Required sync (repository root):**
    ```bash
    git fetch origin master
    git checkout master
    git pull origin master
    git checkout -b fix/short-descriptive-name
    ```
  * After merge of PR #N, **do not push** to that branch again; the next task uses a **new branch name**, **one new first-publish push**, and **`gh pr create`** (new PR number).
  * **Never `git push` to a branch that already backs a PR** — cherry-pick or re-apply the patch onto a **new branch from `master`** instead.
  * If GitHub reports merge conflicts on an open PR, **close** it (or leave it) and open a **fresh PR** from a clean branch off current `master` — do **not** force-push to “fix” the old PR unless the user **explicitly** asked to keep and update **that same PR number**.
  * Before `gh pr create`, confirm `git log origin/master..HEAD` contains only commits for the current deliverable.
  * **PR body encoding check:** use `gh pr create --body-file` (see **PR descriptions: shell / quoting corruption** under Character encoding). After create/edit, run `gh pr view <number> --json body` or open the PR on GitHub — if step names or `$variables` look corrupted, fix with `gh pr edit --body-file`; that is shell quoting, not a repo UTF-8 issue.
* **Pre-push diff review (mandatory — before the one allowed `git push -u` on a new branch):**
  * **Scope:** applies only to the **first publish** of a **new** branch that will become a **new** PR. It does **not** authorize a second push to an existing PR branch.
  * **Read the full diff, not the commit message alone.** A one-line commit can still delete dozens of lines (regression example: a “routing fix” branch that removed FK detach helpers while merging after a correct PR had already landed on `master`).
  * **Required commands (run and inspect output before push):**
    ```bash
    git fetch origin master
    git diff --stat origin/master...HEAD
    git diff origin/master...HEAD
    git log --oneline origin/master..HEAD
    ```
  * **Hard stop — do not run the first-publish push / do not open a PR** when any of these apply unless the user explicitly requested that scope:
    * **Unexpected large deletions** in touched files (e.g. whole `function …` blocks, delete handlers, or helpers removed while the task was “add one attribute” or “fix routing”).
    * **`git diff --stat` shows many more deletions than additions** for a small fix — re-check for a bad `git reset`, wrong merge resolution, or editing the wrong file revision.
    * **The branch would revert code already on `origin/master`** (diff re-introduces removed bugs or strips a just-merged PR).
    * **Two PRs for the same deliverable** — if the correct fix is already on a branch or merged, **abandon** the stale branch (do not merge a second PR). Cherry-pick or recreate from current `master` instead.
  * **After a mistaken first-publish push:** create a **new branch from current `origin/master`**, re-apply only the intended patch, verify diff again, then **one new first-publish push** and **one new PR** — never add commits to the bad branch and push again.
  * **Completion gate:** “ready for first-publish push” means you can summarize every deleted/changed hunk in plain language **and** the branch has **no existing PR**; if you cannot, read the diff again. Deliverable done = **new PR URL**, not “pushed to …/pull/N”.
* **Pre-merge review pass (required before merge):** on every PR, run a targeted review of the changed files (last N files in the diff when large) against this `AGENTS.md`, including at minimum:
  * `php -l` on every touched `.php` file.
  * `php scripts/check_sql_injection_coverage.php` when PHP/SQL changed.
  * `php scripts/check_audit_logs_coverage.php` when CRUD or audit-related paths changed.
  * `php scripts/check_database_sql_company_name_uniques.php` when `database.sql` or tenant unique keys changed.
  * FK label guardrails: no raw `*_id` / `*_by` numeric IDs on list/detail when a label exists; persisted FKs stay selected on edit forms.
  * Module consistency rechecks for any touched module (`index.php`, `view.php`, `edit.php`, `create.php`, `list_all.php`, and `delete.php` when applicable).
  * IDF-related changes: `php scripts/idfs_sync_human_test.php` (or `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\idfs_sync_human_test.php` from the repo root) — hard-fail if any `[FAIL]`.
  * Smoke/CI: `bash scripts/smoke_test.sh` when `.github/workflows/smoke.yml` applies (php -l, CSRF, SQLi only); list exact commands and outcomes in the PR description (do not claim “no tests run” when checks ran).
* **CI and repo scripts stay authoritative:** the smoke workflow must pass on PRs; run other maintenance scripts (for example `check_audit_logs_coverage.php`, `check_database_sql_company_name_uniques.php`, `check_index_table_compliance.php`) manually when the change scope requires them.

### GitHub PR review comments (mandatory)
* **Read all GitHub PR feedback** before considering a PR merge-ready: use `gh pr view`, `gh api repos/{owner}/{repo}/pulls/{number}/comments`, GraphQL review-thread endpoints, or the PR URL. Include human reviewers, **Bugbot**, **Codex**, and actionable CI/check annotations when present.
* **One actionable comment → one fresh Cursor chat:** for each distinct review comment or coherent thread that requests a change, **start a new forked/isolated agent chat** scoped to that item only. Do not mix unrelated review threads in the same session unless they share one root cause.
* **Implement on a fresh branch + new PR:** address the comment with code/docs changes per **NEW PR always** (see **PR review** above). Link the **new** resolving PR in the GitHub reply when applicable — do **not** only push commits to the PR that received the comment unless the user explicitly asked to update that PR.
* **Auto-fix when asked to address review comments:** for each actionable thread, (1) verify whether `master` already contains the fix, (2) if not, patch on a **fresh branch + new PR**, (3) run the relevant checks (`php -l` on touched files, plus any script named in the comment or PR scope), (4) post the GitHub reply below. Do not assume silence means done.
* **Always reply on GitHub with a status label:** every addressed review thread must receive an explicit reply that **starts with exactly** **`Fixed`** or **`Not Fixed`** (that spelling and capitalization). Do **not** use `Fix`, `fix`, `Won't fix`, or other variants—the label must be searchable and consistent.
  * **`Fixed`:** the concern is resolved in a merged commit or an open linked PR; state what changed (file, commit/PR number, or command run).
  * **`Not Fixed`:** intentional deferral, out of scope, or blocked; state why and cite a follow-up issue/PR if planned.
* **Do not leave actionable review threads silent:** if a comment asked for a change, respond with **`Fixed`** or **`Not Fixed`**—never only push code without a labeled GitHub reply.
* **Merged PRs still need replies:** if a PR merged with silent bot/human threads, post retroactive **`Fixed`** / **`Not Fixed`** replies on each thread after verifying `master` (or cite the PR that already fixed it).
* **Passwords Module Security:** All password-related data must be strictly scoped to `user_id` and encrypted at rest using `itm_encrypt` with the user's master key stored in `$_SESSION['vault_key']`.
* **AGENTS.md updates for process rules:** document new review/reply requirements in **`AGENTS.md` on a fresh branch + new PR** (do not fold into an unrelated feature PR).

#### Triage workflow (`gh` CLI)
Use this when asked to “check last N comments and reply” (Codex/Bugbot/human line comments on a PR).

1. **List recent review comments** (line comments on the diff; newest first):
   ```bash
   gh api "repos/pirica/it-management/pulls/comments?per_page=100&sort=created&direction=desc"
   ```
   Filter to bot/human reviewers (exclude your own **`Fixed`** / **`Not Fixed`** replies). For one PR only, use `pulls/{number}/comments` instead of the repo-wide endpoint.
2. **Find threads still missing a reply:** a parent comment has no child with `in_reply_to_id` equal to its `id`. Merged PRs still require retroactive replies on actionable parents.
3. **Verify `master`:** `git fetch origin master` and confirm the concern is already fixed, or open a **fresh branch + new PR** with the patch.
4. **Post a threaded reply** (body must start with **`Fixed`** or **`Not Fixed`**):
   ```bash
   gh api repos/pirica/it-management/pulls/{pr}/comments -X POST \
     -f body="Fixed — …" \
     -F in_reply_to={comment_id}
   ```
   * **`in_reply_to` must be numeric:** use `-F in_reply_to={id}` (typed field), not `-f` (string), or GitHub returns HTTP 422.
   * **PowerShell:** same flags; use two `-f` lines or `-m` for the body if quoting is awkward.
5. **After merge:** add a short follow-up reply on the same thread, e.g. **`Fixed`** — merged to `master` as PR #NNNN, when the fix landed in a follow-up PR.

**Reply templates (searchable labels):**
* **`Fixed`** — merged PR #1708; `scripts/check_ui_configuration_coverage.php` now rejects inverted `perPage >= totalRows` gates.
* **`Fixed`** — PR #1713 on `master`; `modules/system_access/index.php` tbody `ids[]` cells gated with `$showBulkActions`.
* **`Not Fixed`** — intentional per AGENTS.md § Scripts directory (phpMyAdmin linked only from `scripts/scripts.php`, not derived per-request host).

## 📚 API Examples
The `api-examples/` directory contains standalone PHP scripts that demonstrate how to interact with the system programmatically. These scripts are intended as a reference for developers and for automated integration testing.

- **Bulk Imports:** Examples for Equipment, Employees, Tickets, Catalogs, and Events using the `import_excel_rows` JSON payload.
- **Authentication:** Helper scripts for capturing session cookies and CSRF tokens from the login process.
- **CRUD & Filtering:** Examples for archiving tickets, deleting records, viewing single items, and filtering list views (e.g., "Open" tickets).

When adding new API features or complex handlers, always consider adding a corresponding example in `api-examples/` and update `scripts/api.php` to include it in the documentation.

## Cursor Cloud specific instructions

Cloud Agent VMs run Ubuntu 24.04 and do not ship with PHP, MySQL, or Apache pre-installed. The update script (run automatically on VM startup) installs them via `apt-get`; agents do **not** need to repeat those steps.

### Services — how to start after VM boot

| Service | Start command | Verify |
|---------|---------------|--------|
| **MySQL 8.0** | `sudo mkdir -p /var/run/mysqld && sudo chown mysql:mysql /var/run/mysqld && sudo chmod 755 /var/run/mysqld && sudo mysqld --user=mysql --datadir=/var/lib/mysql &` then `sleep 5` | `mysqladmin -u root -pitmanagement ping` → `mysqld is alive` |
| **Apache 2.4** | `sudo apachectl start` | `curl -s -o /dev/null -w '%{http_code}' http://localhost/it-management/login.php` → `200` |

MySQL root password is `itmanagement` (set by the update script on first run). Re-import the schema after a fresh VM with `mysql -u root -pitmanagement --default-character-set=utf8mb4 < database.sql` and verify 88 tables: `mysql -u root -pitmanagement -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='itmanagement';"`.

### Apache alias

The update script creates `/etc/apache2/conf-available/it-management.conf` which aliases `/it-management` to `/workspace`. This matches the `.htaccess` `RewriteBase /it-management/`.

### Smoke tests

Standard commands from `AGENTS.md` § Smoke tests work as-is:

```bash
bash scripts/smoke_test.sh
```

All three checks (php -l lint, CSRF coverage, SQLi coverage) run with the `php` binary on PATH (PHP 7.4.33 via `ondrej/php` PPA).


### Bypassing Login (Dev/Test)

For rapid development or testing, you can bypass the login screen and pre-authenticate as an Admin:

1. Run the bypass script via CLI:
   ```bash
   php scripts/bypass_login.php
   ```
2. The script will output a `Session ID`.
3. Open the app in your browser (e.g., `http://localhost/it-management/`).
4. Open Browser DevTools -> Application -> Cookies.
5. Replace the value of `PHPSESSID` with the `Session ID` from step 2.
6. Refresh the page to be logged in as Admin with Company 1 (TechCorp Global) selected and the Passwords Vault unlocked.

### App login

Default credentials: username `Admin`, password `Admin`. After login, select a company (e.g. TechCorp Global) to access modules.

### Gotchas

* MySQL `dpkg --configure` may hang on first install because systemd service start is blocked in the container. The update script works around this by starting `mysqld` directly and setting the root password manually.
* The `/var/run/mysqld/` directory permissions default to `drwx------` (mysql:mysql) which prevents non-root socket access. The startup sequence above includes `chmod 755` to fix this.
* PHP 7.4 is not the system default on Ubuntu 24.04; `update-alternatives --set php /usr/bin/php7.4` is run by the update script so `php` resolves to 7.4.33.
