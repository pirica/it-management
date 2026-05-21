# AGENTS.md

> [!IMPORTANT]
> **Role:** You are a Senior PHP Developer maintaining a legacy-style Procedural IT Management System.
> **Constraint:** Follow these rules strictly. Do not refactor to OOP, MVC, or modern frameworks. Keep logic flat and modular.

This document provides essential instructions, architectural constraints, and coding standards for AI agents working on the **IT Management System**.

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

## 📂 Directory Map
* `config/`: Core settings and `config.php`.
* `includes/`: UI components (headers, sidebars) and utility functions.
* `modules/`: Feature-specific CRUD logic.
* `scripts/`: Maintenance, security audits, and CLI tools. Catalog: `scripts/index.html`.
* `js/` & `css/`: Assets (use `css/styles.css`).
* **Required Dirs:** `images/`, `tickets_photos/`, and `backups/` must exist with write permissions.
* `scripts/api.php`: API Documentation

### Scripts directory (`scripts/`) — mandatory for every tool

The live catalog is **`scripts/index.html`**. Before merge, **verify every runnable file under `scripts/`** (and any new script you add) against this checklist.

#### 1. Catalog entry in `scripts/index.html` (required for every new script)

Add a table row with:

| Column | Content |
|--------|---------|
| **Script** | Filename (link if browser-safe to open) |
| **Access** | **Browser**, **CLI**, or both; mark **CLI-only** for bash, file writers, or `PHP_SAPI !== 'cli'` guards |
| **What it does** | Plain-language purpose (one short paragraph) |
| **How to use** | Exact browser URL/path, query flags, env vars, and CLI command: `php scripts/<name>.php [options]` |

Do not add a script under `scripts/` without updating `scripts/index.html`.

#### 2. Browser scripts (`scripts/*.php` opened in the browser)

* **Back link (required):** Every HTML report must show **← Scripts index** at the top, linking to `scripts/index.html` (relative `index.html` from `scripts/`).
  * Use `scripts/lib/script_browser_nav.php`: `require_once …/script_browser_nav.php`; then `itm_script_browser_nav_echo()`.
  * Plain-text-in-`<pre>` audits: use `scripts/lib/script_cli_output.php` (`itm_script_output_begin()`), which includes the same nav bar.
* **Human-readable results:** Browser output must explain findings in plain language (not only internal codes). Example: write “Duplicate dropdown option” rather than only `duplicate_dropdown_risk`. Include a short “what to do next” when useful.

#### Link creation rules (browser scripts — mandatory)

All outbound links in HTML script output must use helpers from **`scripts/lib/script_browser_nav.php`**. Do **not** hand-build `<a href="…">` with `BASE_URL`, `itm_script_module_index_url()`, or phpMyAdmin URLs.

| What appears in the report | Create a link? | How (browser) | Example |
|---------------------------|----------------|---------------|---------|
| **← Scripts index** | Always | `itm_script_browser_nav_echo()` | `index.html` (relative) |
| **Module folder** (`floor_plans`, `catalogs`, …) | Always | `itm_script_format_module_link('floor_plans')` or `itm_script_format_module_path_link('modules/catalogs/')` | `../modules/floor_plans/index.php` |
| **Database table name** (`catalogs`, `floor_plan_folders`, …) | **Only if** `modules/<table>/index.php` exists | `itm_script_format_table_link($tableName)` | `catalogs` → module link; `floor_plan_folders` → plain text only |
| **phpMyAdmin** | **Only on `scripts/index.html`** | Hardcode in catalog: `http://localhost/phpmyadmin/` | Never in other `scripts/*.php` output |
| **Edit row / actions** | When useful | `itm_script_module_relative_href_from_path('modules/name/', 'edit.php?id=5')` + `itm_script_external_link_html()` | `../modules/catalogs/edit.php?id=5` |

**Hard rules:**

1. **Relative paths only** for module links from `scripts/` (`../modules/…`). Never use `BASE_URL` or absolute URLs like `https://localhost/it-management/modules/…` in script reports.
2. **`target="_blank"`** and `rel="noopener noreferrer"` on every external/new-tab link — use `itm_script_external_link_html($href, $label)`.
3. **Table name ≠ module name** → no link. Example: table `floor_plan_folders` is not a module folder; show `<code>floor_plan_folders</code>` or plain text. Table `equipment` matches `modules/equipment/` → link the name to that module.
4. **phpMyAdmin** is documented and linked **only** in **`scripts/index.html`** (Laragon local MySQL). Audit/report scripts must not link table names (or anything else) to phpMyAdmin.

* **Exceptions (document in catalog):** JSON-only endpoints (e.g. `test_sql_injection.php`) and CLI entry points that redirect to a UI (e.g. `detect_fk_dropdown_ui_risk.php` → `detect_fk_dropdown_ui_risk_ui.php`) do not need HTML nav on the CLI path.

#### 3. CLI scripts

* Run from repository root: `php scripts/<script>.php [options]`.
* **Windows Laragon** when `php` is not on PATH: `<laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\<script>.php`.
* **Destructive or repo-writing tools** (`normalize_database_sql_created_at.php`, `apply_*_fix.php`, `repair_table_from_schema.php`, etc.): **CLI-only** — block web SAPI with `PHP_SAPI !== 'cli'` and show a small HTML page with **← Scripts index** + CLI instructions if opened in a browser.
* List exact commands and outcomes in the PR description when checks ran.

#### 4. Shared libraries (do not duplicate ad hoc)

| File | Use |
|------|-----|
| `scripts/lib/script_browser_nav.php` | **← Scripts index**, relative module links, table→module links when folder exists (`target="_blank"`) |
| `scripts/lib/script_cli_output.php` | Wrap browser audit output in `<pre>` + shared nav |
| `scripts/lib/utf8_file.php` | UTF-8 writes for `qa-reports/*.md` and `.json` (optional BOM for Windows viewers) |
| `scripts/lib/sql_injection_detector.php` | SQLi signature tests (included by matrix / sandbox tools) |
| `scripts/lib/equipment_type_modules.php` | Canonical `modules/is_*` allowlist (`is_switch`, `is_server`, …); safe removal of regression-test scaffold dirs only (`*_itm_eqdct_*`, `*_itm_edct_*`) |

#### Equipment-type façade modules (`modules/is_*`) and clear-table tests

Canonical equipment-type wrappers live under **`modules/is_*`** (for example `is_switch`, `is_server`, `is_workstation`). They delegate to `modules/equipment/` and must **not** be deleted by maintenance scripts.

| Script | Role |
|--------|------|
| `scripts/lib/equipment_type_modules.php` | Shared allowlist + `itm_remove_equipment_regression_test_module_dirs()` / `itm_ensure_canonical_equipment_type_modules()` |
| `scripts/ensure_equipment_type_modules.php` | Verify or recreate missing canonical `modules/is_*/index.php` wrappers (CLI) |
| `scripts/cleanup_equipment_test_module_artifacts.php` | **CLI-only:** remove test `equipment_types` rows, ITM test companies, junk `is_*_itm_eqdct_*` folders, then re-ensure canonical façades |
| `scripts/equipment_delete_clear_table_test.php` | DB regression for equipment `clear_table` + transactional single delete (use type names **`Switch`** / **`Server`**, not suffixed names) |
| `scripts/employees_delete_clear_table_test.php` | DB regression for employees `clear_table` transaction rollback |
| `scripts/check_equipment_clear_table_delete.php` | Static guard for equipment clear-table helpers (run manually after equipment delete/clear-table changes) |
| `scripts/check_employees_clear_table_transaction.php` | Static guard for employees clear-table transaction (run manually after employees `clear_table` changes) |

**Why tests must not invent new `is_*` folder names:** inserting `equipment_types` named like `Switch itm_eqdct_*` triggers `itm_ensure_equipment_type_module_scaffold()` in `includes/ui_config.php` and pollutes the sidebar. After local DB regression runs, run `php scripts/cleanup_equipment_test_module_artifacts.php`.

#### Smoke tests (CI — `scripts/smoke_test.sh`)

GitHub Actions (`.github/workflows/smoke.yml`) and local CI use **`bash scripts/smoke_test.sh`** — **only** these three checks:

| Step | Command | Purpose |
|------|---------|---------|
| 1 | `php -l` on every `*.php` | Syntax lint |
| 2 | `php scripts/check_csrf_coverage.php` | POST handlers / forms have CSRF |
| 3 | `php scripts/check_sql_injection_coverage.php` | SQLi coverage audit |

Other scripts (`check_index_table_compliance.php`, `check_ui_configuration_coverage.php`, `check_display_field_columns_search.php`, employees/equipment clear-table guards, DB regression tests) are **not** part of smoke — run them manually when the change scope requires it (see `scripts/index.html`).

Optional DB regression (requires MySQL): `php scripts/employees_delete_clear_table_test.php`, `php scripts/equipment_delete_clear_table_test.php`.

#### Full-module browser QA (5 companies, Laragon)

Introduced in [PR #1718](https://github.com/pirica/it-management/pull/1718). Runner FK/delete improvements: [PR #1722](https://github.com/pirica/it-management/pull/1722). Add-step / bulk-order / error-log scope: [PR #1742](https://github.com/pirica/it-management/pull/1742). Bulk skip notes: [PR #1740](https://github.com/pirica/it-management/pull/1740). Sample-data FK parents + seed-only id remap: [PR #1744](https://github.com/pirica/it-management/pull/1744). Use when asked to verify **all modules** across the five seeded companies (TechCorp Global … Enterprise IT).

| Script | Role |
|--------|------|
| `scripts/module_browser_qa_runner.php` | **Browser + CLI:** HTTP session runner — login (`Admin`/`Admin`), company scope, per-module `error_log` scope, FK-aware clear, sample data, **`add`** (random rows capped by unique scope), **`bulk_delete`** after `add` when rows ≥ `records_per_page`, then search/sort/CRUD/export/import/`single_delete`/`clear_table`/end sample restore + `error_log` check. Writes `qa-reports/module-browser-qa-YYYY-MM-DD.json`. Browser: form at the script URL; submit **Run QA** (`?run=1`). |
| `scripts/module_browser_qa_build_report.php` | **Browser + CLI:** Builds markdown from the JSON: summary, **Results by module** (every step Pass/Fail), failure categories, **Failures only** and **Pass only** quick indexes, preview in browser. |

**Commands (repository root, Laragon):**

```bash
php scripts/module_browser_qa_runner.php
php scripts/module_browser_qa_build_report.php
php scripts/module_browser_qa_runner.php --pilot-only
php scripts/module_browser_qa_runner.php --module=expenses --company=4
php scripts/module_browser_qa_runner.php --module=departments --company=1
```

**Browser (Laragon):** `http://localhost/it-management/scripts/module_browser_qa_runner.php` (options form → **Run QA**); `http://localhost/it-management/scripts/module_browser_qa_build_report.php` (pick date → build `.md`). Catalog: `scripts/index.html`.

**Runner browser form (defaults):**

| Field | Control | Default |
|--------|---------|---------|
| **Module** | Select | **ALL (all modules)** — every `modules/<slug>/` with `index.php`; or one slug (e.g. `expenses`) |
| **Company** | Select | **Default company `1` (TechCorp Global)**; **ALL (companies 1–5)** still available in the dropdown |
| **Pilot only** | Checkbox | Off — when checked, runs **`expenses`** only (all selected companies) |

CLI: omit `--module` / `--company` or use `--module=all` / `--company=all` for all modules / all tenants. Browser form defaults to company **1** unless the user selects **ALL**.

**Markdown report (`module_browser_qa_build_report.php`):** after `php scripts/module_browser_qa_build_report.php`, the `.md` under `qa-reports/` includes:

1. **Summary** — pass/fail counts  
2. **Skipped steps** — table of `module_step_exceptions` (module, step slug, plain-language label, reason)  
3. **Failure summary (by step)** — fail count, **Typical cause** (all Tier A steps), and **This run** (parsed from the first matching failure note, e.g. `position_no` out of range for `add`)  
4. **Preflight (company switch)** — company id/name, **OK** / **Failed**, short notes  
5. **Results by module** — per-step tables with slug, label, **OK**/**Failed**, notes  
6. **Failures only (quick index)** — compact table for triage (`Module | Co | Step | Label | Notes`)  
7. **Pass only (quick index)** — same columns for every **Pass** step (truncated at 500 rows; failures index truncated at 200)

**Environment:** `http://localhost/it-management/` with Apache + MySQL (`itmanagement`). The runner uses the same CSRF/login/company session as the browser.

**Tier A step exceptions:** edit `mbqa_runner_module_step_exceptions()` in `scripts/module_browser_qa_runner.php` (module slug → step → N/A note). Mapped steps are **not executed**; all other Tier A steps still run. Examples: `user_companies` skips `create`, `add`, `import_db`; `idf_positions` skips both `sample_data` steps (start + end restore) with note `N/A (HTTP sample seed failed or empty)`.

**Checklist per standard module (Tier A, including Protection Zone folders):** **`error_log`** (rename `error_log.txt` to next `error_log-N.txt` when present; else record byte offset and only fail on *new* lines this module) → **`list`** (index HTTP 200, no fatal) → FK-aware **`clear`** → **`sample_data`** (HTTP; FK parents seeded first — e.g. `expenses` → `departments`, `budget_categories`, `cost_centers`, `gl_accounts`; DB fallback via `itm_seed_table_from_database_sql()` / `itm_seed_resolve_fk_from_database_sql()` when anchor ids differ) → **`add`** (insert ~30 random tenant rows when count &lt; `records_per_page` + 1; grow unique-scope parents first) → **`pagination`** (page=1 **Next** / page=2 **Previous** in HTML when rows &gt; `records_per_page`) → **`bulk_cancel`** (bulk form + **Cancel** contract in index HTML + `js/bulk-delete-selection.js`) → **`bulk_delete`** (when row count after `add` ≥ `records_per_page` and bulk UI + `delete.php` + CSRF: POST `delete.php` with up to 3 `ids[]`; explicit N/A note when skipped) → search → sort → create → view → edit → list_all → **export_pdf** → **export_xls** (parse list table; row count is not import count) → **import_db** (one insertable row smoke test from export headers + `database.sql` FK values when needed; **`inserted=1` is pass**, not full export row count) → **single_delete** (FK retry) → **clear_table** (same row gate as `bulk_delete`) → **`sample_data`** (end restore on empty table) → **`error_log`** (0 new errors since module scope).

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
* **Import Excel** POSTs **one** derived row to `data-itm-db-import-endpoint` (round-trip smoke, not re-import of every exported line). Uses export headers with insertable values from `database.sql` when UI labels are not IDs. **`expenses`:** import picks a **free** `cost_center_id` for the tenant (`uq_expenses_company_scope`); do not expect `inserted` to match export row count.

**Tiers (do not treat all failures alike):**

* **Tier A** — standard flattened CRUD (`modules/<slug>/index.php`), **including Protection Zone** modules (full checklist; module *code* in Protection Zone is still edit-only per AGENTS unless requested).
* **Tier C** — `is_*` façades: full matrix on `is_switch`; routing smoke on the rest.
* **Tier D** — bespoke (`budget_report`, `floor_plans`, `rack_planner`, …): navigation smoke only.

**Cursor browser:** Use IDE browser for the **Expenses pilot** (all five companies) and spot-checks; use the CLI runner for full ~101×5 coverage. Reports live under **`qa-reports/`** (commit dated `.md` + `.json` when publishing QA results).

**Caveats:** Run lookup parents before children (see `$lookupWave` in the runner). Sort-step failures often mean the visible default column is not `id`; confirm via column header links. Modules without `data-itm-db-import-endpoint` report `import_db` as N/A.

#### 5. Pre-merge verification (scripts)

When adding or changing anything under `scripts/`:

1. Confirm a row exists in **`scripts/index.html`** (what / how / access).
2. Open the script in the **browser** (if applicable) — **← Scripts index** visible; module names use `../modules/…`; table names link only when a matching module folder exists; no phpMyAdmin links outside `scripts/index.html`.
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
`/modules/equipment/`, `/modules/idfs/`, `/modules/idf_links/`, `/modules/idf_positions/`, `/modules/idf_ports/`, `/modules/audit_logs/`, `/modules/employees/`, `/modules/settings/`, `/modules/user_companies/`, `modules/employee_system_access/`, `modules/cable_colors/`, `ui_configuration`.

### 4. Dynamic UI Configuration (Settings)
Modules must read/validate settings via `itm_get_ui_configuration()`:
* **Button Positions:** Render refresh/add controls based on `new_button_position`.
* **Table Actions:** Add `data-itm-actions-origin="1"` to "Actions" headers/cells to allow the global layout engine to map `table_actions_position`.
* **DB Import Endpoint (Index Tables):** Add `data-itm-db-import-endpoint="index.php"` to every module index table so `📥Import Excel` can use the save-to-database flow.
* **Global Behaviors:** Respect system toggles for `enable_all_error_reporting`, `enable_audit_logs`, and `records_per_page`.

### 5. Standard Feature Set
Every module (excluding the Protection Zone) must implement:
* **Hide** `company_id` from all UI views.
* **Bulk Actions:** "Select to Delete" and "Clear Table" (visible when row count >= `records_per_page`). Use shared **`js/bulk-delete-selection.js`** (loaded from `includes/header.php`) — see **Bulk delete toolbar and Cancel button** below.
* **Search:** Comprehensive search across all visible fields — see **List/search visible columns** below.
* **Order:** Standardized sort fields ASC DESC - '▲' : '▼'.
* **Tools:** `📗Export Excel`, `📄Export PDF`, and `📥Import Excel` (linked via `js/table-tools.js`).
* **Navigation:** Standardized server-side pagination based on `records_per_page`. List **Previous** / **Next** controls use link text `Previous` and `Next` (preserve `search`, `sort`, `dir`, and `page` query params). **Required `title` attributes:** `title="◀️ Previous"` and `title="▶️ Next"` on pagination anchors (not `🔎 Search`). Pagination URLs include `search=`; `includes/header.php` auto-tooltips must match **visible link text** for Next/Previous and must not treat `search=` in `href` as a Search action. **QA (`pagination` step, after `add`):** when rows > `records_per_page`, verify server HTML on page 1 includes **Next** (`btn-sm`, `page=2`, `title="▶️ Next"`), then page 2 includes **Previous** (`btn-sm`, `page=1`, `title="◀️ Previous"`) — `index.php?search=&sort=id&dir=DESC&page=1` then `page=2`.
* **Error Reporting:** Standardized server-side `enable_all_error_reporting` value from Settings.
* **Enable Audit Log:** `enable_audit_logs` value from Settings.
* **Audit Trail Coverage:** Mandatory INSERT/UPDATE/DELETE logging to `audit_logs` if enabled so changes are traceable in the audit center.

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

**Audit / repair** (catalog: `scripts/index.html`):

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
  * Minimum required checks for CRUD changes: `php -l` on touched PHP files and `php scripts/check_sql_injection_coverage.php`.
  * When changing flattened `index.php` list/search column variables: `php scripts/check_display_field_columns_search.php` (see **List/search visible columns** above).
  * Optional broad QA (all modules × five companies): `php scripts/module_browser_qa_runner.php` then `php scripts/module_browser_qa_build_report.php` — list exact pass/fail counts in the PR when run (see **Full-module browser QA** under `scripts/`).
  * After employees/equipment `clear_table` changes: `php scripts/check_employees_clear_table_transaction.php`, `php scripts/check_equipment_clear_table_delete.php`; optional DB regression per `scripts/index.html` (`employees_delete_clear_table_test.php`, `equipment_delete_clear_table_test.php`). Run `php scripts/cleanup_equipment_test_module_artifacts.php` when equipment regression tests touched the database.
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
      * **Windows Laragon fallback (when `php` is not on PATH):** `<laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\idfs_sync_human_test.php`
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
* **Booleans (List View):** Use badges for status: 
    * `<span class="badge badge-success">Active</span>`
    * `<span class="badge badge-danger">Inactive</span>`
* **Booleans (Edit Mode):** Use icons: `1` = ✅, `0` = ❌.
* **Active Checkbox Guardrail:** In create/edit forms, treat `active` as a checkbox boolean for `tinyint` variants (not only `tinyint(1)`) so the Active toggle is always rendered and normalized reliably.


* **Dynamic Selects:** Enable quick-add functionality: `<option value="__add_new__">➕</option>`.
* **Color Fields:** Use color picker UI: `<input type="color" name="hex_color" id="cable-hex-color-picker" value="#008000">`.
* **Date Fields:** Show date picker UI.

---

## 🛠 Setup & Debugging
* **Dev Credentials:** `localhost` | `root` | `itmanagement`.
* **Online AI Test Environment:**
  * `https://nelsonsalvador.myddns.me` | Login: `Admin` | Password: `Admin`.
  * `http://nelsonsalvador.myddns.me/phpmyadmin/` | Database: `itmanagement` | Login: `root` | Password: (blank).
  * Note: `https://nelsonsalvador.myddns.me/phpmyadmin/` currently returns upstream TLS/certificate errors; use HTTP for phpMyAdmin checks.
* **Logs:** System errors are piped to `ROOT_PATH . 'error_log.txt'`.
* **Testing:** Browser screenshots are not supported; rely on verbose error logging. For full-module CRUD/button regression across five companies, see **Scripts directory → Full-module browser QA** (`module_browser_qa_runner.php`, [PR #1718](https://github.com/pirica/it-management/pull/1718)).
* **CLI scripts:** Run from the repository root with **PHP 7.4.33** and **MySQLi** enabled.
  * **Linux, macOS, CI, and any host where `php` is on PATH:** `php scripts/<script>.php`
  * **Windows (Laragon) when `php` is not on PATH:** use the Laragon 7.4 binary, e.g. `<laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\<script>.php` (replace `<laragon-root>` with your install path; do not use a system PHP 8.x build that lacks MySQLi).

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
* **NEW PR always (mandatory — non-negotiable):**
  * **Every separate request, bugfix, or follow-up** ships on a **fresh branch** and opens a **brand-new pull request**. Do **not** add unrelated commits to an already-open PR “to save time”, and do **not** reuse **PR #N** for **new scope** after merge unless the user explicitly asked to extend that same PR while it is still open.
  * Treat **“always a NEW PR”** literally: **`gh pr create`** (or equivalent) for each deliverable; the prior merged PR is history—**next change = next PR number**.
  * Package every requested implementation in a **fresh branch** and open that **new PR** when the work is ready—do **not** wait for an explicit “please commit” (unless the user asked to hold commits or the session is exploratory/read-only).
  * When required checks pass, **commit**, **push**, and **open the PR** (`gh pr create` when available). A task is not complete with only unstaged or unpushed local changes.
  * Do not reuse a previously opened **pull request** for a **new** request, even if the files overlap.
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
  * After merge of PR #N, **do not push more commits to that branch**; the next task uses a **new branch name** and **`gh pr create`** (new PR number).
  * If GitHub reports merge conflicts on an open PR, prefer **`git fetch origin master`**, rebase or recreate the branch from `master`, and force-push **only while that PR is still open** — or **close the conflicted PR** and open a **fresh PR** from a clean branch (user default: **fresh PR**).
  * Before `gh pr create`, confirm `git log origin/master..HEAD` contains only commits for the current deliverable.
* **Pre-merge review pass (required before merge):** on every PR, run a targeted review of the changed files (last N files in the diff when large) against this `AGENTS.md`, including at minimum:
  * `php -l` on every touched `.php` file.
  * `php scripts/check_sql_injection_coverage.php` when PHP/SQL changed.
  * `php scripts/check_audit_logs_coverage.php` when CRUD or audit-related paths changed.
  * `php scripts/check_database_sql_company_name_uniques.php` when `database.sql` or tenant unique keys changed.
  * FK label guardrails: no raw `*_id` / `*_by` numeric IDs on list/detail when a label exists; persisted FKs stay selected on edit forms.
  * Module consistency rechecks for any touched module (`index.php`, `view.php`, `edit.php`, `create.php`, `list_all.php`, and `delete.php` when applicable).
  * IDF-related changes: `php scripts/idfs_sync_human_test.php` (or the Laragon 7.4 path from Setup) — hard-fail if any `[FAIL]`.
  * Smoke/CI: `bash scripts/smoke_test.sh` when `.github/workflows/smoke.yml` applies (php -l, CSRF, SQLi only); list exact commands and outcomes in the PR description (do not claim “no tests run” when checks ran).
* **CI and repo scripts stay authoritative:** the smoke workflow must pass on PRs; run other maintenance scripts (for example `check_audit_logs_coverage.php`, `check_database_sql_company_name_uniques.php`, `check_index_table_compliance.php`) manually when the change scope requires them.

### GitHub PR review comments (mandatory)
* **Read all GitHub PR feedback** before considering a PR merge-ready: use `gh pr view`, `gh api repos/{owner}/{repo}/pulls/{number}/comments`, GraphQL review-thread endpoints, or the PR URL. Include human reviewers, **Bugbot**, **Codex**, and actionable CI/check annotations when present.
* **One actionable comment → one fresh Cursor chat:** for each distinct review comment or coherent thread that requests a change, **start a new forked/isolated agent chat** scoped to that item only. Do not mix unrelated review threads in the same session unless they share one root cause.
* **Implement on a fresh branch + new PR:** address the comment with code/docs changes per **NEW PR always** (see **PR review** above). Link the resolving PR in the GitHub reply when applicable.
* **Auto-fix when asked to address review comments:** for each actionable thread, (1) verify whether `master` already contains the fix, (2) if not, patch on a **fresh branch + new PR**, (3) run the relevant checks (`php -l` on touched files, plus any script named in the comment or PR scope), (4) post the GitHub reply below. Do not assume silence means done.
* **Always reply on GitHub with a status label:** every addressed review thread must receive an explicit reply that **starts with exactly** **`Fixed`** or **`Not Fixed`** (that spelling and capitalization). Do **not** use `Fix`, `fix`, `Won't fix`, or other variants—the label must be searchable and consistent.
  * **`Fixed`:** the concern is resolved in a merged commit or an open linked PR; state what changed (file, commit/PR number, or command run).
  * **`Not Fixed`:** intentional deferral, out of scope, or blocked; state why and cite a follow-up issue/PR if planned.
* **Do not leave actionable review threads silent:** if a comment asked for a change, respond with **`Fixed`** or **`Not Fixed`**—never only push code without a labeled GitHub reply.
* **Merged PRs still need replies:** if a PR merged with silent bot/human threads, post retroactive **`Fixed`** / **`Not Fixed`** replies on each thread after verifying `master` (or cite the PR that already fixed it).
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
* **`Not Fixed`** — intentional per AGENTS.md § Scripts directory (phpMyAdmin linked only from `scripts/index.html`, not derived per-request host).
