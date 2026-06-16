# Table of Contents

- Scripts Development Standards
  - API documentation (`scripts/api.php`)
  - Pre-implementation discovery (scripts)
  - 1. Catalog Registration
  - 2. Cross-Environment Output (Newline Standard)
    - Coding Standard:
  - 3. Security & Authentication
    - Select Options API verification
  - 4. Path Handling
  - 5. Verification & Testing
      - 1. Catalog entry in `scripts/scripts.php` (required for every new script)
      - 2. Browser scripts (`scripts/*.php` opened in the browser)
      - Link creation rules (browser scripts — mandatory)
      - 3. CLI scripts
      - 4. Shared libraries (do not duplicate ad hoc)
      - Equipment-type façade modules (`modules/is_*`) and clear-table tests
      - Smoke tests (CI — `scripts/smoke_test.sh`)
      - PHPUnit test runner (`scripts/run_tests.php`)
      - HTML coverage guardrails (PHPUnit)
      - Interpreting HTML coverage percentages
      - Full-module browser QA (5 companies, Laragon)
      - 5. Pre-merge verification (scripts)

---

# Scripts Development Standards

> **Canonical source:** All rules for the `scripts/` directory live in this file. **`AGENTS.md` delegates here** — agents must read **`scripts/SCRIPTS.md` completely** at session start and again before any work under `scripts/`. Do not duplicate these standards in `AGENTS.md`; when scripts rules change, edit **only this file**. On conflict, **`SCRIPTS.md` wins** for scripts topics. Laragon PHP/MySQL paths remain in **`AGENTS.md` → Setup & Debugging**.

This document defines the rules for creating and updating tools within the `scripts/` directory.

## Pre-implementation discovery (scripts)

**Mandatory before any scripts work** — aligns with **`AGENTS.md` → Agent compliance workflow → step 4**. Do **not** add, edit, run, or catalog scripts until you have produced, for the task scope:

- **Architectural map** — target script(s), `scripts/lib/` helpers, consumers (modules, CI, MBQA), and whether the tool is browser, CLI, or both.
- **Module summary** — what the script verifies or mutates, protection-zone modules it must not touch, and relevant facts from `scripts/AGENT_NOTES.md` plus any affected module `AGENT_NOTES.md`.
- **Dependency analysis** — `scripts/scripts.php` catalog row, smoke/MBQA impact, shared libs (`script_browser_nav.php`, `script_cli_output.php`, MBQA libs), DB tables, auth/CSRF requirements, and downstream docs (`AGENTS.md`, `docs/`, module notes) that must ship in the same PR.

State the map, summary, and analysis in the agent reply before the first implementation step. Exceptions match **`AGENTS.md` step 4** (read-only/exploratory sessions; documentation-only edits to `SCRIPTS.md` when that is the whole task; single known script with no cross-module impact).

## 1. Catalog Registration
All scripts intended for administrative or developer use must be registered in `scripts/scripts.php`.
- Use the standardized HTML table structure.
- Include appropriate Browser/CLI access badges (`scripts-badge-web`, `scripts-badge-cli`).
- Provide a clear, concise usage description.
- These scripts must support execution via both Browser and CLI and include the standard Navigation Menu (relative back link to `scripts/scripts.php`) using `itm_script_browser_nav_echo()` when viewed in a browser.


## 2. Cross-Environment Output (Newline Standard)
To ensure compatibility between CLI and Browser execution, use a conditional newline string. Hardcoded `\n` or `<br>` are discouraged for generic output.

### Coding Standard:
```php
echo "Message text" . (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
```
Example:
```php
$nl = itm_script_output_nl(); // or: (php_sapi_name() === 'cli' ? "\n" : "<br><br>")
echo colorText('Verifying User Management IDOR...', 'info') . $nl;
echo itm_script_format_status_line('[PASS] Check completed') . $nl;
```
```php
function colorText($text, $type) {
    $isCli = (php_sapi_name() === 'cli');

    switch ($type) {
        case 'pass':
            return $isCli
                ? "\033[32m$text\033[0m"   // green
                : "<span style='color: green;'>$text</span>";

        case 'fail':
            return $isCli
                ? "\033[31m$text\033[0m"   // red
                : "<span style='color: red;'>$text</span>";

        case 'warn':
            return $isCli
                ? "\033[33m$text\033[0m"   // yellow
                : "<span style='color: goldenrod;'>$text</span>";

        case 'info':
            return $isCli
                ? "\033[34m$text\033[0m"   // blue
                : "<span style='color: dodgerblue;'>$text</span>";

        default:
            return $text;
    }
}
```


## 3. Security & Authentication
- Scripts that perform destructive actions or access sensitive data MUST include role-based access control.
- Check for the 'Admin' role using session variables (e.g., `$_SESSION['role_name']`).
- Use `itm_require_post_csrf()` for all state-changing `POST` requests.
- For CLI scripts, use the `ITM_CLI_SCRIPT` constant to bypass web-specific authentication when appropriate.

### Select Options API verification

| Script | Purpose |
|--------|---------|
| `php scripts/verify_select_options_escalation.php` | Regression — a non-admin session cannot create an Admin user via `modules/select_options_api.php` by POSTing `table=users` with `role_id` and `access_level_id` in `extra_fields`. Expects **PASS** (`[PASS] … blocked by table whitelist`) when `includes/itm_select_options_policy.php` rejects the target table before INSERT. |

Run after changes to `modules/select_options_api.php` or `includes/itm_select_options_policy.php`. Requires MySQL (`itmanagement` schema). The script creates disposable test users and removes them on exit. Catalog: `scripts/scripts.php`.

## 4. Path Handling
- Always use `dirname(__DIR__)` or `ROOT_PATH` to resolve absolute paths.
- Avoid platform-specific separators; use `DIRECTORY_SEPARATOR` or normalize to forward slashes.
- **Upload / tenant file trees:** do not call bare `mkdir()` for `images/`, `tickets_photos/`, `floor_plans/`, `backups/`, or `files/`. Use `itm_ensure_upload_directory()` / `itm_ensure_upload_directory_chain()` / `itm_ensure_files_storage_directory()` from `includes/bootstrap_helpers.php`.
- **Every project folder must have empty `index.html`:** applies to **every directory under the repository root** (`modules/`, `includes/`, `css/`, `js/`, upload trees, etc.). Folders that already have `index.php` still get `index.html`. Skips VCS/metadata dot dirs (`.git`, `.github`, …). Upload paths additionally receive managed `.htaccess`.
- **Force-create contract:** every `itm_ensure_upload_directory()` call **overwrites** both managed `.htaccess` (policy body) and an empty `index.html` on that folder. Applies to all policies and every chain segment. Never add `.htaccess` or `index.html` manually after `mkdir()`.
- **Managed `.htaccess` policies:** `upload` (public assets), `deny_http` (`files/` chain), `deny_all` (`backups/`). Canonical Apache bodies and markers: **`docs/file_upload_modules.md`** (human-readable) and `itm_upload_directory_policy_body()` in `includes/bootstrap_helpers.php` (code source of truth).
- **Policies summary:** `upload` — static files allowed, script execution blocked; `deny_http` — `RewriteRule ^ - [F]` per `files/` segment; `deny_all` — `Require all denied`.
- **Backfill entire project:** `php scripts/empty_folders.php` — repairs empty `index.html` on every project folder; lists only **new or changed** repo-relative `…/index.html` paths before the summary; upload roots also get `.htaccess`.
- **Backfill `files/` only:** `php scripts/ensure_files_htaccess_chain.php`. See `docs/file_upload_modules.md` for the full module/storage map.

### Explorer verification scripts

| Script | Purpose |
|--------|---------|
| `php scripts/test_explorer_paths.php` | Pure-logic regression for `get_full_path` ACL (roots, traversal, backslashes) |
| `php scripts/test_explorer_preview.php` | Pure-logic regression for Explorer preview routing (`image`, `pdf`, `text`, `unsupported`) |
| `php scripts/verify_explorer_zip_leak.php` | Confirms `downloadZip` cannot zip `Private` / company root |
| `php scripts/verify_explorer_rce_htaccess.php` | PoC — malicious `.htaccess` upload must be blocked or overwritten |
| `php scripts/verify_explorer_rce_marker.php` | PoC — `.htaccess` with ITM marker cannot persist RCE directives |

Run path/ZIP checks after Explorer ACL changes. PoC scripts restore `deny_http` via `itm_ensure_files_storage_directory()` after tests. Catalog: `scripts/scripts.php`.

## 5. Verification & Testing
- New scripts should ideally be accompanied by a unit test or a verification PoC.


#### 1. Catalog entry in `scripts/scripts.php` (required for every new script)

Add a table row with:

| Column | Content |
|--------|---------|
| **Script** | Filename (link if browser-safe to open) |
| **Access** | **Browser**, **CLI**, or both; mark **CLI-only** for bash, file writers, or `PHP_SAPI !== 'cli'` guards |
| **What it does** | Plain-language purpose (one short paragraph) |
| **How to use** | Exact browser URL/path, query flags, env vars, and CLI command: `php scripts/<name>.php [options]` |

Do not add a script under `scripts/` without updating `scripts/scripts.php`.

#### API documentation (`scripts/api.php`)

Browser-only HTML catalogue of **implemented** JSON/AJAX endpoints. Update **`scripts/api.php`** in the same deliverable when adding or changing:

- Explorer file actions (`modules/explorer/api.php`, `file.php`, `downloadZip`)
- Shared includes (`get_ports.php`, `update_port.php`)
- `modules/select_options_api.php`, passwords vault AJAX, notes/todo `ajax_action` handlers
- IDF `modules/idfs/api/*` handlers
- Module `import_excel_rows` JSON import endpoints
- API key auth and tier rate limits (`includes/itm_api_rate_limit.php`, `GET scripts/api.php?rate_limit=1`)

**Collector helpers** (unit-tested in `phpunit/tests/Unit/Scripts/ApiFunctionsTest.php`):

| Function | Purpose |
|----------|---------|
| `itmDocCollectModuleImportEndpoints()` | Scan `modules/*/index.php` and `list_all.php` for import handlers |
| `itmDocCollectExplorerApiActions()` | Parse Explorer `switch` actions from live `modules/explorer/api.php` |
| `itmDocCollectIdfApiEndpoints()` | List IDF API files with purpose blurbs |
| `itmDocProjectJsonEndpoints()` | Curated non-import AJAX endpoints |
| `itmDocPasswordsApiActions()` / `itmDocNotesAjaxActions()` / `itmDocTodoAjaxActions()` | Module-specific action matrices |
| `itmDocCollectApiExamples()` | Scan every `api-examples/*.php` file (title/category/purpose table in `api.php`) |
| `itmDocApiRateLimitTiers()` | Tier → hourly limit table for API key documentation |

**Verify after API doc changes:**

```bash
php -l scripts/api.php
php scripts/run_tests.php --filter ApiFunctionsTest
```

Open `scripts/api.php` in the browser and confirm Explorer, IDF, and import tables render.

#### 2. Browser scripts (`scripts/*.php` opened in the browser)

* **Special Case: `scripts/health.php` (MANDATORY):** This script is used for automated health monitoring and must NOT be modified. Do not add navigation links, headers, or any logic that changes its output format.
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
* Run from repository root: `php scripts/<script>.php [options]` (Linux/macOS/CI); on **Windows Laragon** use the **full PHP binary path** from **`AGENTS.md` → Setup & Debugging → PHP CLI tests**.
* **Windows Laragon (mandatory for tests):** `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe` — always use this full path when running scripts locally; in **PowerShell** prefix with **`&`**; list the exact shell command in PR test plans (see **`AGENTS.md` → Setup & Debugging → PHP CLI tests**).
* **`PHP_BINARY` for sub-processes:** When a script needs to execute another PHP script, prefer using the **`PHP_BINARY`** constant to ensure the same PHP version is used.
* **Destructive or repo-writing tools** (`normalize_database_sql_created_at.php`, `apply_module_sample_data_seed.php`, `apply_*_fix.php`, `repair_table_from_schema.php`, etc.): **CLI-only** — block web SAPI with `PHP_SAPI !== 'cli'` and show a small HTML page with **← Scripts index** + CLI instructions if opened in a browser.
* List exact commands and outcomes in the PR description when checks ran.

#### 4. Shared libraries (do not duplicate ad hoc)

| File | Use |
|------|-----|
| `scripts/lib/script_browser_nav.php` | **← Scripts index**, relative module links, table→module links when folder exists (`target="_blank"`) |
| `scripts/lib/script_cli_output.php` | Wrap browser audit output in `<pre>` + shared nav; `itm_script_output_nl()`, `colorText()`, `itm_script_format_status_line()` |
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

Module seed expansion in `database.sql` (repo write, no DB mutation): `php scripts/apply_module_sample_data_seed.php --module=<module_name> [--sample=name[:emoji] ...] [--dry-run]`. Use this to automate per-company lookup seed additions (updates inserts only).

#### PHPUnit test runner (`scripts/run_tests.php`)

Central runner for the suite under `phpunit/tests/Unit/` using `phpunit/phpunit.phar` and `phpunit/phpunit.xml`. Catalog row: **`scripts/scripts.php`**.

| Mode | Browser | CLI |
|------|---------|-----|
| **Standard** (verbose, no coverage) | Open `scripts/run_tests.php` → **Standard** | `php scripts/run_tests.php` |
| **HTML coverage** | **HTML coverage** (needs Xdebug or PCOV) | `php scripts/run_tests.php --coverage` or `ITM_COVERAGE=1` |
| **Skip DB tests** | Checkbox **Skip database tests** | `ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php` |

**Coverage report:** after a successful HTML coverage run, open **`phpunit/coverage/html/coverage.html`** (PHPUnit writes `index.html`; `run_tests.php` renames it to `coverage.html`). The browser menu and post-run output link to this path when the file exists.

**Browser URLs:**

| Action | URL |
|--------|-----|
| Choose run mode | `scripts/run_tests.php` |
| Standard verbose run | `scripts/run_tests.php?run=1&mode=standard` |
| HTML coverage | `scripts/run_tests.php?run=1&mode=coverage` |
| Skip DB + coverage | `scripts/run_tests.php?run=1&mode=coverage&skip_db=1` |

**Coverage driver:** `run_tests.php` checks `extension_loaded('xdebug') || extension_loaded('pcov')` before passing `--coverage-html`. Without a driver it runs with `--no-coverage` and shows a note (avoids PHPUnit’s “No code coverage driver available” warning). On Laragon: Menu → PHP → Extensions → enable Xdebug or PCOV, restart Apache.

**Windows Laragon (PowerShell):**

```powershell
cd C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\run_tests.php
& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\run_tests.php --coverage
```

Linux/macOS/CI: bare `php scripts/run_tests.php` when PHP 7.4 is on PATH.

**PHPUnit config:** `phpunit/phpunit.xml` — `verbose="true"`, `<coverage processUncoveredFiles="false">` (avoids bare-`require` of hundreds of uncovered module/script entry files during HTML report generation), HTML output under `coverage/html`. Shared guards: `includes/itm_script_entry_guard.php`. See also `phpunit/AGENT_NOTES.md` and `phpunit/tests/PREFERENCES.md`.

##### HTML coverage — report generation guardrails

If HTML coverage finishes tests (`OK (… tests, … assertions)`) but fails or warns during **Generating code coverage report in HTML format …**, uncovered files were being executed with side effects (historically with `processUncoveredFiles="true"`).

Common symptoms:

| Symptom | Typical file |
|---------|----------------|
| `Cannot modify header information - headers already sent` | `includes/get_ports.php`, `includes/update_port.php`, `includes/companies_view_redirect.php` |
| `Cannot redeclare fetch_lookup_map()` | `get_ports.php` + `update_port.php` (shared helpers now in `includes/switch_port_api_helpers.php`) |
| HTML fragment + `Undefined variable: conn` / type error on `mysqli` | View partials such as `includes/itm_it_location_linked_floor_plans.php` |

**Root causes:**

1. **`PasswordsFunctionalTest.php`** ran procedural code with **`echo` at file load time** (fixed — guard entry scripts).
2. **Bare HTTP entry scripts** called **`header()` / `echo` / `exit` at top level** (fixed — use **`includes/itm_script_entry_guard.php`** (`itm_skip_http_entry_unless_direct()`)).
3. **View partials** output HTML without context — use **`itm_skip_view_partial_unless_context()`** or explicit `$conn` guard.
4. **Duplicate top-level `function` declarations** across two endpoints — PHP registers functions at compile time; consolidate in a shared helper file.
5. **`processUncoveredFiles="true"`** — PHPUnit bare-`require`s every uncovered file under `config/`, `includes/`, `modules/`, `scripts/`; disabled in `phpunit.xml` so the HTML report completes reliably. Files executed during tests still appear in the report.

**Fixes (keep these patterns):**

| Area | Rule |
|------|------|
| **Test files** | Use proper **`PHPUnit\Framework\TestCase`** classes with `test*` methods and assertions — **no top-level execution**, **no `echo`** in files matched by `suffix="Test.php"`. |
| **Redirect / AJAX entry scripts** | Guard **before** `require config` using **`itm_skip_http_entry_unless_direct(__FILE__)`** then **`return`**. Use **`dirname(__DIR__) . '/config/config.php'`** (not relative `../config/`). |
| **View partials** | **`itm_skip_view_partial_unless_context()`** or **`return`** when `$conn` missing — before any HTML. |
| **Shared endpoint helpers** | One file with **`function_exists`** wrappers (e.g. `includes/switch_port_api_helpers.php`). |

**When adding includes under coverage paths:** run HTML coverage once (`php scripts/run_tests.php --coverage` or browser **HTML coverage** with Xdebug/PCOV) and confirm **`phpunit/coverage/html/coverage.html`** is created without warnings, notices, or fatals.

##### Interpreting HTML coverage percentages

A successful run (e.g. **OK (239 tests, 968 assertions)** on Laragon with MySQL) often shows **low overall totals** such as **~0.4% lines** and red **“danger”** badges on the dashboard. That is **expected** with the current suite and `phpunit/phpunit.xml` scope — it does **not** mean coverage collection failed.

**Why totals look low**

| Factor | Effect |
|--------|--------|
| **Wide `<coverage><include>`** | Counts executable lines in all of `config/`, `includes/`, `modules/`, `scripts/` (~180k+ lines). |
| **Module tests use MySQLi directly** | Most `phpunit/tests/Unit/Modules/*Test.php` files INSERT/SELECT/DELETE via `$conn`; they do **not** HTTP-load `modules/*/index.php`, `create.php`, etc. So **`modules/` stays near 0%** even when DB behaviour is tested. |
| **Bootstrap loads `config.php`** | **`config/`** tends to be the highest bucket (~15–20% lines) because every test bootstraps through it. |
| **Functional / script tests** | **`includes/`** and **`scripts/`** rise slightly when tests `require` handlers (e.g. passwords AJAX, audit scripts). |
| **Report colour thresholds** | `phpunit.xml` uses `lowUpperBound="50"` and `highLowerBound="80"` — almost all buckets show **Low (danger)** until coverage improves or scope is narrowed. |

**Typical dashboard shape (full suite + DB, Xdebug/PCOV)**

| Area | Approx. lines covered | Notes |
|------|------------------------|--------|
| **Total** | &lt; 1% | Normal baseline today |
| **config** | ~15% | Bootstrap + shared config paths |
| **includes** | ~1% | Helpers/endpoints hit by functional tests |
| **modules** | &lt; 0.2% | CRUD tests touch DB, not module PHP entry files |
| **scripts** | ~2–4% | Script tests + runner includes |

**Goals vs baseline**

- **`phpunit/tests/PREFERENCES.md`** lists **80% minimum** as a **long-term target**, not the current measured baseline.
- Use the HTML report to see **which files/lines tests actually execute** (green/red line markers), not only the headline percentage.

**Raising coverage (optional)**

1. Add tests that **`require` or HTTP-exercise** module entry files (functional/browser QA), not only mysqli CRUD.
2. Run **`scripts/module_browser_qa_runner.php`** for HTTP-level module coverage (separate from PHPUnit HTML report).
3. Narrow `<coverage><include>` in `phpunit/phpunit.xml` (e.g. `includes/` + changed module only) when you want a **focused** percentage for one deliverable — do not expect high **Total** % while the whole tree remains included.

#### Full-module browser QA (5 companies, Laragon)

Full-module browser QA runs HTTP session checks across the five seeded companies (TechCorp Global … Enterprise IT). The runner includes FK-aware clear/delete, add-step and bulk-order coverage, scoped `error_log` attribution, bulk-step skip notes, and sample-data FK parent seeding with seed-only id remap. Use when asked to verify **all modules** across those companies.

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

**FK-aware clear / delete:**

* **Tenant clear** walks inbound FKs (`information_schema`), deletes child rows for the active `company_id`, then clears the module table. MySQL **1451** retries parse the **child table** from the error text (`` `schema`.`child_table` ``), not the schema name.
* **`single_delete`** POSTs `delete.php`; on “in use by: `employee_positions` (1)” it clears parsed blocker tables (or `itm_find_record_usage`) and retries — **including Protection Zone tables** when required to unblock the delete.
* **Never auto-clear** during FK prep or delete retry: **`companies`** and **`users`** only (shared auth).
* **Skip destructive clear** on `companies` and `users` at the start of each module (same as before).

**Sample / export / import:**

* Sample seed prerequisites are seeded first when configured (e.g. `expenses` → `departments`, `budget_categories`, `cost_centers`, `gl_accounts`; `employee_positions` → `departments`).
* **`error_log`:** If `error_log.txt` cannot be renamed (e.g. Windows file lock), the runner records the current file size and only attributes **new** lines to the active module — avoids false failures from earlier modules. When rotation succeeds, archives are `error_log-1.txt`, `error_log-2.txt`, … under `ROOT_PATH`.
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
