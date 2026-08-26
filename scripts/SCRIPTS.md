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
      - Full scripts test matrix (`scripts/SCRIPTS_TEST_MATRIX.md`)
      - 5. Pre-merge verification (scripts)
      - 6. File Upload Modules

---

# Scripts Development Standards

> **Canonical source:** All rules for the `scripts/` directory live in this file. **`AGENTS.md` delegates here** — agents must read **`scripts/SCRIPTS.md` completely** at session start and again before any work under `scripts/`. Do not duplicate these standards in `AGENTS.md`; when scripts rules change, edit **only this file**. On conflict, **`SCRIPTS.md` wins** for scripts topics. **Script catalog rows (What / How / Access)** live only in **`scripts/scripts.php`** — link there instead of copying tables into this file. Laragon PHP/MySQL paths remain in **`AGENTS.md` → Setup & Debugging**.

This document defines the rules for creating and updating tools within the `scripts/` directory.

## Pre-implementation discovery (scripts)

**Mandatory before any scripts work** — aligns with **`AGENTS.md` → Agent compliance workflow → step 4**. Do **not** add, edit, run, or catalog scripts until you have produced, for the task scope:

- **Architectural map** — target script(s), `scripts/lib/` helpers, consumers (modules, CI, MBQA), and whether the tool is browser, CLI, or both.
- **Module summary** — what the script verifies or mutates, bespoke modules it skips or special-cases, and relevant facts from `scripts/AGENT_NOTES.md` plus any affected module `AGENT_NOTES.md`.
- **Dependency analysis** — `scripts/scripts.php` catalog row, smoke/MBQA impact, shared libs (`script_browser_nav.php`, `script_cli_output.php`, MBQA libs), DB tables, auth/CSRF requirements, and downstream docs (`AGENTS.md`, `docs/`, module notes) that must ship in the same PR.

State the map, summary, and analysis in the agent reply before the first implementation step. Exceptions match **`AGENTS.md` step 4** (read-only/exploratory sessions; documentation-only edits to `SCRIPTS.md` when that is the whole task; single known script with no cross-module impact).

## 1. Catalog Registration

**Single source of truth:** **`scripts/scripts.php`** holds every catalog row (Script link, Access badges, **What it does**). For **`scripts/*.php`**, **How to use** lives in each entry script (`itm_script_browser_how_to_use()`) and is shown on the browser landing page (dodgerblue info) before **Dry-run** / **Continue** (`run=1`; apply scripts also offer `apply=1` on Continue). Catalog PHP rows use a stub fifth column (`scripts-catalog-how-stub`). Non-PHP documentation rows keep full **How to use** in the catalog. Do **not** duplicate catalog columns in `SCRIPTS.md`, `scripts/AGENT_NOTES.md`, or module notes — link to the catalog and put behavioral contracts only in `SCRIPTS.md`. **`scripts/SCRIPTS_TEST_MATRIX.md`** classifies cataloged scripts by tier (not a second catalog).

All scripts intended for administrative or developer use must be registered in `scripts/scripts.php`.
- Use the standardized HTML table structure.
- Include appropriate Browser/CLI access badges (`scripts-badge-web`, `scripts-badge-cli`).
- Provide a clear, concise usage description.
- These scripts must support execution via both Browser and CLI and include the standard Navigation Menu (relative back link to `scripts/scripts.php`) using `itm_script_browser_nav_echo()` when viewed in a browser.

### Catalog table tags (scripts.php search)

Each catalog card shows **table tags** derived from static analysis of the linked script:

| Distinct schema tables via `$conn` | Tag(s) |
|---|---|
| 0 (no schema table references, bash, or external link) | `Codebase` |
| `.py` catalog entry (Playwright / screenshot utilities) | `Python` |
| `.sh` catalog entry (bash / CI wrappers) | `Server` |
| `.json` / `.txt` row under **Documentation** (`scripts/data/*` or companion data files) | `Info` |
| `.md` row under **Documentation** (`scripts/*.md`, `scripts/data/*.md`) | `Markdown` |
| 1 | table name (e.g. `employees`) |
| 2 | both table names |
| 3+ | `Mixed` |

**Scan scope:** PHP `$conn` SQL + requires + spawn targets + filename tokens for `*.php` rows. **Info / Markdown apply to documentation file rows only** (not inferred from PHP references). Auto-list data/docs files with `apply_script_catalog_documentation_files.php`.

**UI:** Search row (`?q=` + live filter) with **Search** submit and emoji-only **🔙** clear; hides empty section cards while filtering. Sticky nav includes **← Admin** / **← Dashboard**. Extension search/chips (`*.json`, `*.txt`, `*.md`, `Info`, `Markdown`) match catalog hrefs ending in `.json`, `.txt`, or `.md` inside **Documentation** (`#docs`). General search matches filename/href substring; SQL-style `%` / `_` wildcards match the catalog link path (e.g. `%scripts_errors%`, `verify_%`).

**Maintenance:**

| Script | Purpose |
|--------|---------|
| `php scripts/apply_script_catalog_documentation_files.php` | Dry-run; `--apply` inserts `scripts/data/*.{json,txt,md}` + `scripts/*.md` under **Documentation** |
| `php scripts/apply_script_catalog_tags.php` | Dry-run; `--apply` writes `scripts/data/script_catalog_tags.json` and patches catalog markup |
| `php scripts/check_script_catalog_tags.php` | Exit `1` when tags drift from computed scan |
| `php scripts/verify_scripts_catalog_filter.php` | Scrape `scripts/scripts.php`; simulate `*.json` / `*.txt` / `*.md` search and chip filters; fail on CSS column drift |
| `python scripts/verify_scripts_catalog_filter_screenshot.py` | Playwright human-flow: runs the PHP verifier first, then captures PNGs under `qa-reports/scripts_catalog_filter/` and asserts visible row counts for Info / `*.json` / `*.txt` / `*.md` filters. Env: `ITM_SCREENSHOT_BASE_URL`, `ITM_PHP_BIN`, `ITM_PYTHON_BIN` |

**Overrides:** `scripts/data/script_catalog_tags.json` — set `"override": true` on a slug when static analysis cannot see dynamic table names.

Re-run **apply** after adding catalog rows or changing script SQL; run **check** in pre-merge verification when `scripts/scripts.php` or `scripts/*.php` SQL changes.


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
- Use `itm_require_post_csrf()` for all state-changing `POST` requests. Public auth pages that must show in-page errors use `itm_try_post_csrf()` (same validation, no bare `exit` before HTML). Forms must emit the token with `itm_get_csrf_token()` — never read `$_SESSION['csrf_token']` directly in markup or compare POST tokens manually unless you call `itm_validate_csrf_token($_POST['csrf_token'] ?? '')` (same contract as `itm_require_post_csrf()`). Static gate `php scripts/check_csrf_coverage.php` treats `itm_try_post_csrf()`, `itm_require_post_csrf()`, and `itm_validate_csrf_token()` as CSRF guards.
- For CLI scripts, use the `ITM_CLI_SCRIPT` constant to bypass web-specific authentication when appropriate. **Exception:** `scripts/scripts.php` sets `ITM_CLI_SCRIPT` only under CLI SAPI — the browser catalog must not define it (keeps normal session/auth). Browser access is **admin-only** via `itm_is_admin()`; non-admins get HTTP 403 HTML with links back to dashboard (session is not cleared).
- **Global scripts bootstrap:** `scripts/lib/itm_script_bootstrap.php` — loaded from `config/config.php`. **Browser + CLI** is the default for `scripts/*` (normal session/auth in the browser; `ITM_CLI_SCRIPT` only under CLI SAPI). **Browser execution swaps to a disposable test Admin or test employee** (`itm_script_begin_browser_isolated_session()`) — never the signed-in Admin cookie; shutdown restores the real session and deletes the disposable row. **`csrf_token` must survive the swap:** on begin, copy the pre-swap token into the isolated session when present; when forms call `itm_get_csrf_token()`, sync the minted token into the pre-swap backup; on shutdown (`itm_script_finish_browser_isolated_session()`), merge the isolated token back into the restored real session; `itm_validate_csrf_token()` also accepts the pre-swap backup token during POST so GET form → POST round-trips validate. Exempt: `scripts.php`, `api.php`, MBQA runner, PHPUnit menu. Scripts that must stay CLI-only use their own `PHP_SAPI !== 'cli'` guard (or `itm_script_prepare_cli_entry()`). Disposable test-user sessions (`apitest-user-*`, `script-*`, slot ids `999901+`) may only browse `scripts/*.php`. In-process session tests use `itm_script_with_test_session_context()` / `itm_script_publish_isolated_http_session()` — **never** the signed-in Admin browser session.
- **No-auth browser scripts:** define `ITM_SCRIPT_NO_AUTH` before `config.php` only for read-only aggregate diagnostics allowlisted in `config/config.php` (`$itmNoAuthScripts`). Currently: `count_db_tables.php`, `test_chatbot.php`.

### Database table count (`count_db_tables.php`)

| Script | Purpose |
|--------|---------|
| `php scripts/count_db_tables.php` | Counts live tables in `information_schema` for `itmanagement`, echoes the total as plain text, and overwrites `scripts/number_db_tables.txt`. Fresh `db/` import: **225** tables (must match `CREATE TABLE` count in `db/01_schema.sql`; [count_db_tables.php](http://localhost/it-management/scripts/count_db_tables.php) / [verify_database_schema.php?run=1](http://localhost/it-management/scripts/verify_database_schema.php?run=1)). Browser and CLI; **no login** (exempt from `run=1` usage landing — see `itm_script_browser_usage_exempt_basenames()`). |
| `php scripts/verify_count_db_tables_recon.php` | Low-impact recon contract: `count_db_tables.php` stays no-auth digits-only output (no table-name enumeration). |

Catalog: `scripts/scripts.php`.

### Path and directory audits

| Script | Purpose |
|--------|---------|
| `php scripts/empty_folders.php` | Backfill empty `index.html` on every project folder (excluding dot dirs). Upload paths also receive managed `.htaccess`. |
| `php scripts/ensure_files_htaccess_chain.php` | Backfill `deny_http` managed `.htaccess` and empty `index.html` on every directory segment under `files/`. |
| `php scripts/perform_audit.php` | **Exploratory** subprocess audit of Tier 1–3 `scripts/*.php` (excludes Tier 4 MBQA, Tier 5 maintenance, `repro_*`, `verify_*`, `_tmp_*`, `health.php`, and session-mock harnesses `test_ajax.php` / `test_edit.php`). Uses Laragon `php.exe`, `putenv()` DB vars, truncates `error_log.txt`, per-script log deltas, and PHP-specific stdout filters. Report: `scripts/php_error_audit_results.json` (`exit_code`, `cli_errors`, `stdout_hits`, `summary`). Intentional exit-only failures: `scripts/data/perform_audit_allowlist.json`. Exit `0` when no unallowlisted PHP errors or non-zero exits. Optional `--loop` prints top failures and exits `1` for agent triage. **Not a CI gate** — run Tier 1 checks and targeted `verify_*` / `repro_*` separately. |
| `php scripts/titles_list.php` | Scans all PHP files under the `modules/` directory to extract their `<title>` tags. Prints a summary (scanned / with title / match vs not match) for the canonical title `<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>`, then lists each file; non-matching rows are prefixed `[NOT MATCH]`. |
| `php scripts/titles_list_show.php` | Same scan and summary as `titles_list.php`, but lists rendered inner title text (substitutes `$crud_title` and app name where possible). Non-matching rows are prefixed `[NOT MATCH]`. |
| `php scripts/list_active_and_checkboxes.php` | Audits `active` field UI for modules whose resolved `$crud_table` has an `active` column. Skips bespoke slugs (`itm_crud_tables_load_skip_module_slugs()`) and auto-scaffold stub dirs (`itm_module_dir_is_standard_crud_scaffold()`). Flags forbidden `<input type="text" name="active">`, scaffold checkboxes missing `itm-checkbox-control`, and status-driven modules (`employees`, `equipment`, `patches_updates`, `tickets`) with a visible row `active` checkbox on create/edit. Status-driven scaffold is compliant when forms call `itm_crud_render_form_hidden_active_input()` and omit `active` from `$formColumns` (business status on `status_id` / `employment_status_id` → `*_status`). CLI `--json`, `--all`; exit `1` on violations. |
| `php scripts/fix_scaffold_active_checkbox.php` | Repairs `scaffold_active_checkbox_not_compliant` rows from the audit above. Browser module select + dry-run/apply; CLI `--module=<slug>` or `--all`, then `--apply`. Re-check with `list_active_and_checkboxes.php`. |
| `php scripts/pitfalls.php` | Aggregated pitfalls and developer traps from every `AGENT_NOTES.md` in the repository (modules, config, includes, scripts, phpunit, css, js, root, `.github`, and other in-scope folders). Backfills missing note files under `modules/` only. Supports Browser (Admin) and CLI with `-module=<slug|path>` and `--json`. Reviewed empty §10 sections may use `[Confirmed] No pitfalls documented` so the report shows an explicit confirmation instead of the generic empty placeholder. Prunes top-level upload/runtime trees (`files/`, `backups/`, `images/`, `tickets_photos/`, root `floor_plans/`) but still scans module folders with the same basename (e.g. `modules/floor_plans/`). |
### Schema fields and type listings

| Script | Purpose |
|--------|---------|
| `php scripts/list_boolean_integer_fields.php` | Parses both `db/` and the live database to list fields of Boolean, int, tinyint, and other numeric types, matching tables to modules by name. |
| `php scripts/list_enum_fields.php` | Parses both `db/` and the live database to list ENUM fields, matching tables to modules by name. |
| `php scripts/list_empty_tables.php` | Lists tenant-scoped tables with zero live rows for the session company (browser Admin) or `--company=N` (CLI). Browser filter: `?company=N` (dropdown on page). Links to `modules/{table}/index.php` in a new tab when the module folder exists. JSON: `--json` / `?company=N&format=json`. |
| `php scripts/list_db_tables_sample_data.php` | Parses `db/01_schema.sql` and `db/02_data_sample.sql` (no MySQL). Lists every schema table with module slug link (`target="_blank"`), `sample_data` yes/no/exempt/n/a, and `[INFO]` summary lines on CLI. Browser: Admin; click column headers to sort; filter `?sample=no` / `--sample=no`; server/CLI sort `--sort=table|slug|sample_data` + `--dir=asc|desc`. Shared builder: `scripts/lib/itm_database_tables_sample_data_report.php`; sort UI: `scripts/lib/itm_script_report_table_sort.php`. |
| `php scripts/list_db_tables_sample_data_company.php` | Same schema/sample map plus live tenant row counts for one company. Multi-company filter: browser dropdown / `?company=N` / `--company=N` (defaults to session). Filters: `--sample=no`, `--tenant=empty`. Click column headers to sort; `--sort=table|slug|sample_data|tenant|tenant_rows` + `--dir=asc|desc`. Requires MySQL. Builder: `scripts/lib/itm_database_tables_sample_data_company_report.php`. |
| `php scripts/list_db_tables_without_modules.php` | Lists every live table in `DB_NAME` with no `modules/{table}/index.php` and no module `$crud_table` mapping (policy-hidden internal tables excluded — same rules as `compare_database_sql_modules.php`). Browser: Admin login; JSON: `--json` / `?format=json`; exit `1` when gaps exist. Shared builder: `scripts/lib/itm_database_tables_modules_report.php`. |
| `php scripts/compare_database_sql_modules.php` | Full bidirectional audit: every `CREATE TABLE` in `db/01_schema.sql` vs `modules/` folders and `$crud_table` mappings (matched, missing module, missing table, mismatch). Browser: Admin login; CLI `--json`, `--all`; exit `1` on gaps. Shared builder: `scripts/lib/itm_database_tables_modules_report.php`. |
| `php scripts/list_modules_without_share.php` | Lists `modules_registry` rows **not** in `itm_qr_share_capable_module_slugs()` (Share Modules **No share UI**). Browser: Admin login; module **name** links to `modules/{slug}/index.php`. CLI: `--json`, `--active-only`. See `docs/CRUD_RECORD_SHARE.md`. |
| `php scripts/list_module_decorated_links.php` | Finds modules that **actually render** decorated inline `<a>` links (not `btn`, not `itm-plain-link`, not sort-header `color:inherit`). **Method:** runtime probe of `cr_render_cell_value()` with each module's `$crud_table` + `db/01_schema.sql` columns, plus template scan outside that function. Prints `[INFO] link={slug}` with indented `[INFO]   {file}:{line} href=… source=…` per hit (clickable module URL in browser). CLI: `--module=slug`, `--json`. Browser: [list_module_decorated_links.php?run=1](http://localhost/it-management/scripts/list_module_decorated_links.php?run=1). Lib: `scripts/lib/itm_module_decorated_links_report.php`. **Tier 2 gate:** `php scripts/check_module_decorated_links.php` (exit `1` when any remain). **Bulk repair:** `php scripts/apply_module_decorated_plain_links.php --apply`. |
| `php scripts/verify_company_empty_sample_data.php` | Regression: for a tenant (`--company=N` or browser `?company=N`), seeds every empty module that exposes **Add sample data** via `itm_seed_lookup_parents_for_table()` + `itm_seed_table_from_database_sql()`; exits `1` on failure. Optional `--module=slug` / `?module=slug`. Browser requires Admin login. Pairs with `list_empty_tables.php` before/after tenant onboarding checks. |
| `php scripts/extract_by_fields.php` | Parses `db/` to extract column definitions containing keywords like `by`, `to`, `employee_id`, `employee`. Output is formatted and saved to `scripts/fields_by.txt`. |
| `php scripts/scaffold_departments_child_table_modules.php` | One-time/maintenance: copy flattened CRUD from `modules/departments/` into child/support table modules (24-table allowlist). **Default = dry-run** (compact summary on `?run=1`; use `--verbose` / `?verbose=1` to list every slug); writes with CLI `--apply` or browser `?run=1&apply=1` (Admin). Pair with `verify_scaffold_departments_child_table_modules.php`. |
| `php scripts/scaffold_schema_gap_table_modules.php` | Copy flattened CRUD from `modules/departments/` into schema tables missing `modules/{table}/` (215-table audit gap: API key scopes, approval inbox items, automation rule runs, hotel portal UI copy satellites, problem-management children, etc.). **Default = dry-run**; writes with `--apply` / `?run=1&apply=1` (Admin). Lib: `scripts/lib/itm_scaffold_schema_gap_table_modules.php`. |
| `php scripts/verify_scaffold_departments_child_table_modules.php` | Three-step gate: module `index.php` + `$crud_table`, `db/01_schema.sql` tables-without-module count, sidebar catalog ids. Exit `1` on failure. |
| `php scripts/scaffold_hotel_booking_crud_modules.php` | Maintenance / bulk patcher: scaffold Hotel Booking CRUD modules from `modules/customer_statuses` template. Default dry-run. |
### Select Options API verification

| Script | Purpose |
|--------|---------|
| `php scripts/verify_select_options_escalation.php` | Regression — a non-admin session cannot create an Admin employee via `modules/select_options_api.php` by POSTing `table=employees` with `role_id` and `access_level_id` in `extra_fields`. Expects **PASS** (`[PASS] … blocked by table whitelist`) when `includes/itm_select_options_policy.php` rejects the target table before INSERT. **PASS JSON body:** `{"ok":false,"error":"This list cannot be updated from quick-add."}` (not a `302` to `login.php`). Browser catalog runs use `itm_resolve_cli_php_binary()` (`PHP_EXE` in `.env` when Apache’s `PHP_BINARY` is php-cgi). |

Run after changes to `modules/select_options_api.php` or `includes/itm_select_options_policy.php`. Requires MySQL (`itmanagement` schema). The script creates disposable test users and removes them on exit. Catalog: `scripts/scripts.php`.

| Script | Purpose |
|--------|---------|
| `php scripts/repro_select_options_unauthorized_v2.php` | Regression — regular users cannot quick-add `companies` via Select Options API. Embedded scenario matrix then live subprocess (browser prefers Laragon CLI `php.exe`, not Apache `php-cgi`). Policy fallback when subprocess still unusable. |
| `php scripts/repro_attempts_data_leak_v2.php` | Regression — password-like login identifiers are redacted before `attempts.email` persistence. Disposable secret per run; verifies only the row inserted by this request (no brittle `ip_address` filter; ignores proxy headers during simulation). Browser + CLI. |
| `php scripts/repro_destructive_import.php` | Repro — employees import must not delete rows missing from payload (company 1). **Browser + CLI dry-run default**; browser dry-run `?run=1`, apply `?run=1&apply=1` (Admin; `?apply=1` deep-link also sets `run=1`); CLI `--apply` seeds two disposable employees via `itm_script_test_employee_create()`, imports only Keep Me, asserts Delete Me survives, then tears down disposable rows. |
| `php scripts/repro_vault_corruption.php` | Regression — vault master key re-encryption rolls back on failure; entries stay decryptable with the old key. Seeds disposable user + `password_entries` rows with `company_id` (NOT NULL). Browser-safe errors (no `STDERR`). |

Run after login/forgot-password attempt logging changes, Select Options policy updates, vault master key / TOTP change logic in `user-config.php` / `includes/itm_vault_master_key.php` / `includes/itm_totp_helpers.php` / `includes/itm_vault_unlock.php`, or employees import changes.

**TOTP PHPUnit:** `php scripts/run_tests.php --filter TotpTest` — secret generation, encrypt/decrypt round-trip, employee-row verification (`phpunit/tests/Unit/Security/TotpTest.php`; disposable user via `itm_script_test_employee_create()`).

**Departments quick-add (`__add_new__`):** whitelisted in `includes/itm_select_options_policy.php`. `select_options_api.php` auto-inserts `company_id` (when `data-add-company-scoped="1"`) and `active=1`. Only **`name`** is required in the modal (`new_value`); **`code`** is optional via `data-add-extra-fields` (`required: false`). Used on equipment create/edit (`modules/equipment/create.php`).

### Disposable script test users

Repro, verify, and PHPUnit tests must **not** mutate seed user id `1` (Admin) or other live accounts. Use **`scripts/lib/itm_script_test_employee.php`**:

| Helper | Purpose |
|--------|---------|
| `itm_script_test_employee_username($scriptSlug)` | Unique username `script-{slug}-{hex}` |
| `itm_script_test_employee_create($conn, $companyId, $options)` | INSERT disposable `employees` row (clears stale `@app_employee_id` first) |
| `itm_script_test_employee_snapshot($conn, $employeeId, $columns)` | Read sensitive columns before mutation |
| `itm_script_test_employee_restore($conn, $employeeId, $snapshot)` | Restore prior values |
| `itm_script_test_employee_delete($conn, $employeeId)` | DELETE row (disposable prefix only; clears audit actor first) |
| `itm_script_test_employee_register_teardown($conn, $employeeId, $snapshot)` | Shutdown restore + delete |
| `itm_script_test_employee_clear_audit_context($conn)` | `SET @app_employee_id` / `@app_company_id` to NULL (avoids `audit_logs` FK failures) |
| `itm_script_test_employee_set_audit_context($conn, $employeeId, $username, $companyId)` | `SET @app_employee_id` / `@app_company_id` / `@app_username` (rejects id ≤ 0) |
| `itm_script_test_employee_create_session_actor($conn, $companyId, $options)` | Disposable Admin/employee session actor (`as_admin`, `script_slug`) + `employee_companies` grant — browser script isolation + PHPUnit |

**PHPUnit session isolation:** `phpunit/tests/Unit/Support/ItmPhpunitTestSessionTrait.php` — `itmPhpunitBeginTestSession()` / `itmPhpunitCreateDisposableSessionActor()` / `itmPhpunitEndTestSession()`; never `$_SESSION['employee_id'] = 1`.

**Stale SQL guard:** `php scripts/check_stale_user_id_sql.php` — fails when `modules/`, `includes/`, or `config/` PHP still references legacy `user_id` column SQL or the removed `users` table after the employees merge. Run after auth/session or schema merge changes; catalog: `scripts/scripts.php`.

**Stale terminology guard:** `php scripts/check_stale_user_terminology.php` — fails when `scripts/`, `docs/`, or `phpunit/tests/` still say `Users module` / `Users Management`, when `includes/database_sql_unique_audit.php` special-cases `employee_companies.user_id`, when `modules/` PHP uses `strtolower($_SESSION['role_name'])` for admin checks instead of `itm_is_admin()`, when `cr_username_for_user_id` remains in module code, or when CRUD `$hidden` arrays still list `'user_id'`. Catalog: `scripts/scripts.php`.

**CRUD hidden-column alias:** `php scripts/apply_crud_hidden_employee_id_alias.php` — one-time/idempotent maintenance replacing dead `'user_id'` entries in flattened module `$hidden = [...]` arrays with `'employee_id'`. Re-run when new scaffolds copy the old template. Catalog: `scripts/scripts.php`.

**PHPUnit:** `ItmScriptTestUserTest.php`, `ReproAuditDisclosureTest.php`; security repro tests in `VulnerabilityVerificationTest.php` use the same helper. All `phpunit/**/AGENT_NOTES.md` files document this contract.

**Related:** `scripts/lib/itm_api_tier_test_helpers.php` (disposable `ui_configuration` slots only); `includes/itm_mbqa_test_user.php` (MBQA runner row tags).

### Global scripts bootstrap (`scripts/lib/itm_script_bootstrap.php`)

Loaded from **`config/config.php`** on every request. Enforces the contract that **CLI regressions use disposable test-user sessions — never the signed-in Admin browser session**.

| Helper | Purpose |
|--------|---------|
| `itm_script_is_cli()` | `PHP_SAPI === 'cli'` or `phpdbg` |
| `itm_script_running_under_scripts_dir()` | True when `SCRIPT_FILENAME` is `scripts/*.php` |
| `itm_script_browser_skip_web_auth_allowlist()` | `module_browser_qa_runner.php`, `run_tests.php` — may skip web auth on localhost / `ITM_MAINTENANCE_TOKEN` |
| `itm_script_browser_isolation_exempt_basenames()` | Catalog/API/MBQA scripts that keep the signed-in browser session |
| `itm_script_begin_browser_isolated_session($conn, $skipWebAuth)` | Browser `scripts/*`: swap to disposable test Admin/employee; copies `csrf_token` into isolated session when present; shutdown restores real session and merges isolated `csrf_token` back |
| `itm_script_finish_browser_isolated_session()` | Shutdown hook: delete disposable employee, merge isolated `csrf_token` into pre-swap backup, restore real `$_SESSION` |
| `itm_script_sync_csrf_to_browser_session_backup($token)` | When `itm_get_csrf_token()` runs under isolation, mirror the token into the pre-swap backup for shutdown merge and POST validation |
| `itm_script_get_browser_authorization_employee_id()` | Real signed-in employee id for Admin authorization gates |
| `itm_script_require_admin_browser_or_exit($conn)` | HTML 403 when the **real** browser caller is not Administrator |
| `itm_script_session_or_authorization_is_admin($conn)` | True for disposable test Admin session or pre-swap authorization employee |
| `itm_script_require_admin_script_or_exit($conn, $message)` | Plain-text 403 admin gate for `scripts/*` |
| `itm_script_is_disposable_test_session()` | Detects `apitest-user-*`, `script-*-{hex}`, or slot ids `999901–999999` in `$_SESSION` |
| `itm_script_reject_disposable_test_web_session_or_exit($currentFile, $skipWebAuth)` | Clears disposable test cookie on normal web pages; **allowed** on `scripts/*.php` |
| `itm_script_with_test_session_context($companyId, $employeeId, $username, $callback)` | Temporary test-user `$_SESSION` for in-process asserts; restores prior session (Admin) after |
| `itm_script_publish_isolated_http_session($companyId, $employeeId, $username)` | Writes a throwaway `sess_*` file for curl/browser HTTP probes without mutating the active session |
| `itm_script_prepare_cli_entry($basename)` | CLI-only guard + `ITM_CLI_SCRIPT` define; caller must `require config.php` at **file scope** next |

**Browser + CLI entry include:** `scripts/lib/itm_script_regression_entry.php` — `ITM_CLI_SCRIPT` on CLI only; Administrator required in browser after `config.php`. Alias: `itm_script_cli_entry.php`.

**Admin-gated `scripts/*` (browser):** after `config.php`, call `itm_script_require_admin_script_or_exit($conn)` (or pass a custom plain-text message). Do **not** gate with `itm_is_admin($conn, (int)$_SESSION['employee_id'])` alone — disposable test Admin sessions and pre-swap authorization employees are accepted via `itm_script_session_or_authorization_is_admin()`. Exceptions: `scripts.php` (catalog checks the **real** signed-in Admin with custom HTML recovery copy) and CLI-only utilities that validate a target user row (e.g. `bypass_v2.php`).

**CLI-only scripts** — bash wrappers (`smoke_test.sh`, `import_database_split.sh`, …), session hijack helpers (`bypass_login.php`, `bypass_v2.php`), catalog listing-only repro tools, or `itm_script_prepare_cli_entry()` before `config.php`. Repo/DB writers that previously blocked the browser now use **`itm_apply_script_bootstrap.php`** (dry-run default) instead of a hard CLI-only guard.

**Skip-web-auth allowlist** (localhost / `ITM_MAINTENANCE_TOKEN`): `module_browser_qa_runner.php`, `run_tests.php`. Regression: `php scripts/verify_script_localhost_maintenance_auth.php`.

**Recovery:** if the dashboard shows company info but **No companies available** and `scripts.php` returns admin 403, the browser cookie was likely replaced by a script test user — sign out and log in again as Admin.

**PHPUnit:** `phpunit/tests/Unit/Scripts/ItmScriptBootstrapTest.php`.

### Security repro scripts (validated findings)

| Script | Purpose |
|--------|---------|
| `php scripts/repro_rbac_bypass.php` | PoC — read-only Expenses user must not delete via `delete.php` (expects PASS: HTTP 403 message + row retained, or permission-helper fallback when browser subprocess hits login redirect). Seeds a disposable expense with tenant-scoped `paid_status_id`, `gl_account_id`, `posting_date`, and unique `invoice_number` (respects `uq_expenses_company_scope`). Subprocess uses Laragon CLI `php.exe`, restores `$_SESSION` before `config.php`, and sets `SCRIPT_NAME` / `DOCUMENT_ROOT`. |
| `php scripts/repro_employee_companies_bac.php` | PoC — non-admin must not access `employee_companies` index (expects PASS after `itm_require_admin()` on all entry files). |
| `php scripts/repro_employee_companies_leak.php` | PoC — multi-tenant leak checks for Employees module. |
| `php scripts/repro_cross_tenant_admin.php` | PoC — company-2 Admin Employees list must not include a disposable company-1 username (expects `[PASS]`; seeds via `itm_script_test_employee_create_session_actor()`, Laragon CLI subprocess HTML probe). Browser: `?run=1`. |
| `php scripts/repro_db_integrity.php` | Regression — expenses UNIQUE allows different `posting_date`/`invoice_number` (requires tenant `paid_status_id`); documents `employee_assignment_history` one-row-per-employee unique as WARN; Admin bookmark trigger via disposable Admin. Transaction + rollback. Browser: `?run=1`. |
| `php scripts/check_crud_rbac_coverage.php` | Static audit — in-scope flattened `modules/*/index.php` delete/create/edit handlers must call `itm_require_crud_role_module_permission()` (or accepted alternate guards such as `itm_require_admin()`). Exempt slugs: `itm_crud_rbac_exempt_module_slugs()`. Exit `1` when missing. |
| `php scripts/apply_crud_rbac_guards.php` | **Browser + CLI.** Default dry-run; `--apply` / `?apply=1` (Admin). Lists changed files and RBAC-exempt modules. Bulk-insert CRUD RBAC guards on flattened index handlers (idempotent). |
| `php scripts/repro_auth_bypass_v3.php` | PoC — non-admin must not reach companies/users delete flows. Subprocess spawn uses `escapeshellarg()`. |
| `php scripts/repro_vulnerabilities.php` | PoC — Explorer RCE, privilege escalation, role-module access; browser + CLI via `script_cli_output.php` and isolated Laragon CLI subprocesses. |
| `php scripts/repro_esa_vulnerability.php` | PoC — employee system access vulnerability checks. Subprocess spawn uses `escapeshellarg()`. |
| `php scripts/repro_audit_token_leak.php` | Verification — audit log must not store plaintext `reset_token`; disposable test user via `lib/itm_script_test_employee.php`; prepared `UPDATE employees` for token fields. |
| `php scripts/repro_employee_dataloss.php` | Regression — generic `itm_handle_json_table_import()` UPDATE must not NULL-out omitted columns on `employees` (expects exit `0`; seeds/disposable row in transaction). |
| `php scripts/repro_generic_dataloss.php` | Regression — generic JSON import UPDATE must not NULL-out omitted columns (e.g. `departments.code`; expects exit `0`; seeds/disposable row in transaction). |
| `php scripts/repro_contacts_idor.php` | PoC — IDOR in contacts API inline edit; disposable attacker/victim via `itm_script_test_employee_*` (clears audit actor before INSERT to avoid `audit_logs` FK failures) |
| `php scripts/repro_select_options.php` | PoC — RBAC bypass in select options API. Standardized with `itm_script_output_begin()`. |
| `php scripts/repro_status_leak.php` | PoC — cross-tenant employee status leak. |
| `php scripts/repro_visitors_bac.php` | PoC — Broken Access Control in visitors access log. |
| `php scripts/repro_visitors_sqli.php` | PoC — SQL Injection in visitors access log inline edit. |
| `php scripts/verify_audit_updated.php` | Verification — audit log redaction of sensitive fields. |
| `php scripts/verify_audit_logs_disclosure.php` | Three-step employees audit disclosure regression: static `db/03_triggers.sql` employees trigger scan, live disposable employee UPDATE probe, retro scan of recent `employees` audit rows. Prints each step; optional `ITM_TEST_COMPANY_ID`. |
| `php scripts/verify_status_leak_fixed.php` | Verification — fixed scoping for employee status. |
| `php scripts/verify_visitors_bac_fix.php` | Verification — blocked unauthorized visitor log additions (against live module). |
| `php scripts/verify_visitors_sqli_fix.php` | Verification — fixed SQL Injection in visitors access log. |
| `php scripts/verify_sqli_updated.php` | Verification — SQL Injection fix in visitors access log against fixed files. |
| `php scripts/verify_rbac_updated.php` | Verification — RBAC protection guards in module handlers. |
| `php scripts/verify_import_fix_updated.php` | Verification — Employee Import Department Data Loss Fix. |
| `php scripts/repro_bug.php` | Bug reproduction and verification script for Todo module visibility and security. |
| `php scripts/repro_rce.php` | PoC for RCE in Floor Designer via `save_as_floor_plan` (subprocess + `images/switch_port_icons/*.png` sample; `[PASS]` when ext=php is coerced to png). Uses project `.env` for DB (no hardcoded credentials). |
| `php scripts/repro_sqli.php` | PoC for SQL Injection in Floor Designer via 'dir' parameter. |
| `php scripts/repro_bac.php` | PoC for cross-tenant BAC in IDFs API `position_delete` (company 1 user vs company 2 position). Uses project `.env` for DB (no hardcoded credentials). |
| `php scripts/repro_birthdays_resignations_rbac.php` | PoC & verification — unprivileged users cannot bypass Birthdays and Resignations view controls. Executable in both Browser and CLI environments (redirects to `dashboard.php` if accessed directly in the browser, or checks redirect status gracefully using isolated subprocesses). |

Repro and verify runners that spawn temporary PHP subprocesses use `escapeshellarg()` on the PHP binary and temp file path. Stderr discard uses `itm_script_shell_stderr_discard()` from `scripts/lib/script_cli_output.php` (`2>/dev/null` on Unix, `2>NUL` on Windows). Catalog: `scripts/scripts.php`. PHPUnit mirror: `VulnerabilityVerificationTest.php`.

## 4. Path Handling
- Always use `dirname(__DIR__)` or `ROOT_PATH` to resolve absolute paths.
- Avoid platform-specific separators; use `DIRECTORY_SEPARATOR` or normalize to forward slashes.
- **Upload / tenant file trees:** do not call bare `mkdir()` for `images/`, `tickets_photos/`, `floor_plans/`, `backups/`, or `files/`. Use `itm_ensure_upload_directory()` / `itm_ensure_upload_directory_chain()` / `itm_ensure_files_storage_directory()` from `includes/bootstrap_helpers.php`.
- **Every project folder must have empty `index.html`:** applies to **every directory under the repository root** (`modules/`, `includes/`, `css/`, `js/`, upload trees, etc.). Folders that already have `index.php` still get `index.html`. Skips VCS/metadata dot dirs (`.git`, `.github`, …). Upload paths additionally receive managed `.htaccess`.
- **Force-create contract:** every `itm_ensure_upload_directory()` call **overwrites** both managed `.htaccess` (policy body) and an empty `index.html` on that folder. Applies to all policies and every chain segment. Never add `.htaccess` or `index.html` manually after `mkdir()`.
- **Managed `.htaccess` policies:** `upload` (public assets), `deny_http` (`files/` chain), `deny_all` (`backups/`). Canonical Apache bodies and markers: **`scripts/AGENT_NOTES.md`** (human-readable) and `itm_upload_directory_policy_body()` in `includes/bootstrap_helpers.php` (code source of truth).
- **Policies summary:** `upload` — static files allowed, script execution blocked; `deny_http` — `RewriteRule ^ - [F]` per `files/` segment; `deny_all` — `Require all denied`.
- **Backfill entire project:** `php scripts/empty_folders.php` — repairs empty `index.html` on every project folder; lists only **new or changed** repo-relative `…/index.html` paths before the summary; upload roots also get `.htaccess`.
- **Backfill `files/` only:** `php scripts/ensure_files_htaccess_chain.php`. See `scripts/AGENT_NOTES.md` for the full module/storage map.

### News module maintenance

| Script | Purpose |
|--------|---------|
| `php scripts/generate_ms_support_feed_products.php` | Scrapes Microsoft Support [RSS feed picker](https://support.microsoft.com/en-us/rss-feed-picker) product `<option>` GUIDs and regenerates `includes/itm_news_feed_ms_support_products.php` (205 Atom feeds merged into `news_feed_source_catalog()`). |

### Database Schema validation scripts

| Script | Purpose |
|--------|---------|
| `php scripts/schema_report.php` | Visual HTML report of schema validation errors, warnings, and skips (SKIP DELETE CASCADE). |
| `php scripts/validate_DB_schema.php` | Static validation of FKs, duplicate indexes, and orphaned indexes on employee_id; prints `[SKIP]` for intentional CASCADE. |
| `php scripts/test_employee_id-foreign_keys.php` | Runtime validation of employee_id FKs and scoping. |
| `php scripts/validate_delete_employee.php` | Checks if employees can be safely deleted by auditing referencing FKs and triggers. |

### Employee data maintenance scripts

| Script | Purpose |
|--------|---------|
| `php scripts/generate_FK_employee_id.php` | Detects missing employee_id FKs and suggests ALTER TABLE SQL. |
| `php scripts/generate_reassignment.php --employee-id=N [--to=M]` | Reassignment **plan** before employee delete: row counts, skip reasons, optional related-column SQL, inbound FK debug. **Default dry-run** + **rows-only filter** (`all_tables=1` to show zeros). `--apply` runs `employee_id` UPDATEs when `--to` is set. Browser: Admin form with company + employee id filter. |
| `php scripts/transfer_data_from_employee.php --id=N` | Clones an employee and copies related rows to the new record. **Browser + CLI dry-run default** (`?id=N` or form preview); `--apply` / `?apply=1` / form **Apply copy** (Admin) commits the clone and copies all `employee_id` tables (excludes `audit_logs`, `attempts`). |
| `php scripts/delete_clone_employee.php --id=N` | Reverses a clone by deleting related `employee_id` rows and the employee. **Browser + CLI dry-run default**; `--apply` / `?apply=1` / form **Apply delete** (Admin). |
| `php scripts/verify_clear_table_fix.php` | Verification — employees clear-table soft-delete + bookmark detach. |

### Equipment & Audit verification scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_equipment_triggers.php` | Regression — verifies that INSERT, UPDATE, and DELETE operations on the `equipment` table are correctly logged to `audit_logs` via database triggers. Performs a full lifecycle test with a disposable test user and handles its own test data cleanup. |

### Explorer verification scripts

| Script | Purpose |
|--------|---------|
| `php scripts/test_explorer_paths.php` | Pure-logic regression for `get_full_path` ACL (roots, traversal, backslashes, `./` prefix bypass) |
| `php scripts/test_explorer_preview.php` | Pure-logic regression for Explorer preview routing (`image`, `pdf`, `text`, `unsupported`) |
| `php scripts/test_explorer_paths.php` | Path ACL logic for Explorer (`get_full_path`). Browser + CLI. |
| `php scripts/explorer_human_test.php` | Human-flow Explorer API regression (list/create/rename/move/copy/delete, ACL, DB soft-delete sync, audit). **Mutates DB + filesystem** (temporary company). Browser (Admin) via `lib/itm_script_regression_entry.php`; disposable test user via `itm_script_with_test_session_context()`; coloured pass/fail log inside `<pre>` from `itm_script_output_begin()` (`itm_script_output_nl()` + `itm_script_format_status_line()`). CLI; exit `1` on failure. |
| `php scripts/verify_explorer_zip_leak.php` | Step 1: blocked roots (Home, `Common`, `Private`, `Departments`, `Trash`). Step 2: exact `Private/{username}_{id}` only (session `vault_key` required — Explorer vault gate). Step 3: all other paths blocked (own subfolders, `Common`/`Departments`, other users). Subprocess harness: `itm_resolve_cli_php_binary()`, session before `config.php`. |
| `php scripts/verify_explorer_profile_photo_acl.php` | Explorer `file.php` profile ACL: same-tenant peer may read `Private/{user}/profile/` thumbnails; cross-tenant reader without `employee_companies` grant gets **403**; non-profile Private paths stay owner-scoped. |
| `php scripts/repro_explorer_path_bypass_v4.php` | Regression — `./Private` and `./Private/{other}` blocked after path normalization |
| `php scripts/repro_explorer_zip_slip_v2.php` | Regression — malicious ZIP traversal entries blocked during `unzip` |
| `php scripts/verify_explorer_rce_htaccess.php` | PoC — malicious `.htaccess` upload must be blocked or overwritten |
| `php scripts/verify_explorer_rce_marker.php` | PoC — `.htaccess` with ITM marker cannot persist RCE directives |
| `php scripts/verify_explorer_updated.php` | Verification — Explorer file extension whitelisting. |
| `php scripts/repro_explorer_traversal.php` | Repro — Explorer Path Traversal vulnerability via 'item' parameter. |
| `php scripts/verify_explorer_fix.php` | Verification — Explorer Path Traversal fix. |
| `php scripts/verify_explorer_fix_updated.php` | Verification — Updated Explorer Path Traversal fix. |
| `php scripts/verify_explorer_fix_web.php` | Verification — Web-friendly Explorer Path Traversal fix. |
| `php scripts/verify_explorer_fix_standalone.php` | Verification — Standalone Explorer Path Traversal fix (HTML UI). |

Run path/ZIP checks after Explorer ACL or trash UI changes. Isolated subprocess spawns use `escapeshellarg()`. PoC scripts restore `deny_http` via `itm_ensure_files_storage_directory()` after tests. Catalog: `scripts/scripts.php`. PHPUnit trash leaf filter: `ExplorerTest::testTrashListFiltersAncestorFolders`.

## 5. Verification & Testing
- New scripts should ideally be accompanied by a unit test or a verification PoC.


#### 1. Catalog entry in `scripts/scripts.php` (required for every new script)

Add a table row with:

| Column | Content |
|--------|---------|
| **Script** | Filename (link if browser-safe to open) |
| **Access** | **Browser** + **CLI** (most `scripts/*.php`), or **CLI-only** (bash, Python, session hijack helpers) — see catalog badges in `scripts/scripts.php` |
| **What it does** | Plain-language purpose (one short paragraph) |
| **How to use** | **Non-PHP** rows only: exact browser URL/path, query flags, env vars, and CLI command. **PHP** rows: stub text — open the script in the browser for the landing page (see **Browser script usage landing** below). |

Do not add a script under `scripts/` without updating `scripts/scripts.php`.

#### Browser script usage landing (`scripts/lib/itm_script_browser_usage.php`)

- Each browser-capable **`scripts/*.php`** defines **`itm_script_browser_how_to_use(): string`** (plain text or safe HTML). Maintenance: **`php scripts/apply_script_catalog_usage_to_php.php --apply`** moves legacy catalog copy into PHP files and stubs catalog column 5.
- **First browser GET** (no `run=1`): **← Scripts index**, **How to use** block (`.itm-script-usage-info`, **dodgerblue**), then **🔍** Dry-run (`run=1`, no `apply`) and **▶️** Continue (`run=1`; **apply** scripts add `apply=1` on Continue — Admin gate unchanged).
- **CLI** skips the landing. **Exempt** basenames: `itm_script_browser_usage_exempt_basenames()` (catalog, API docs, MBQA runners, full HTML apps such as `pitfalls.php` and `update_all_created_at.php`, no-auth plain-text monitor `count_db_tables.php`).
- **Hooks:** `itm_apply_script_bootstrap()` (apply tools, `supports_apply` true), `itm_script_output_begin()` (audit scripts), `itm_script_regression_entry.php`, `itm_check_script_begin_browser_admin()`.
- **Static gate:** `php scripts/check_script_browser_usage.php` — usage function + hook + catalog stub per browser PHP row (skips exempt basenames).
- **Static gate:** `php scripts/check_script_stdio_fwrite.php` — no raw `fwrite(STDOUT|STDERR)` under `scripts/`; use `itm_script_write_stdout()` / `itm_script_write_stderr()` from `scripts/lib/itm_script_stdio.php` (loaded via `script_cli_output.php`).
- **Static gate:** `php scripts/check_script_php_utf8_no_bom.php` — no UTF-8 BOM at the start of `scripts/**/*.php` (BOM before `<?php` breaks `declare(strict_types=1)` and `php -l` on PHP 7.4).

#### API documentation (`scripts/api.php`)

Browser-only HTML catalogue of **implemented** JSON/AJAX endpoints. Update **`scripts/api.php`** in the same deliverable when adding or changing:

- Explorer file actions (`modules/explorer/api.php`, `file.php`, `downloadZip`)
- Shared includes (`get_ports.php`, `update_port.php`)
- `modules/select_options_api.php`, passwords vault AJAX, notes/todo `ajax_action` handlers
- IDF `modules/idfs/api/*` handlers
- Module `import_excel_rows` JSON import endpoints
- API key auth and tier rate limits (`includes/itm_api_rate_limit.php`, `GET scripts/api.php?rate_limit=1`)
- Partner hotel distribution API (`modules/hotel_booking_api/api.php`), outbound integration webhooks (`docs/INTEGRATION_WEBHOOKS.md`), and module JSON handlers (`modules/appointments/api.php`, `modules/live_chat/api.php`, `modules/notifications/api.php`, `modules/search/api.php`, `modules/ticket_sla_dashboard/api.php`, `modules/bookmarks/list_all.php`, `modules/knowledge_base/chat_api.php`)

**Collector helpers** (unit-tested in `phpunit/tests/Unit/Scripts/ApiFunctionsTest.php`):

| Function | Purpose |
|----------|---------|
| `itmDocCollectModuleImportEndpoints()` | Scan `modules/*/index.php` and `list_all.php` for import handlers |
| `itmDocCollectExplorerApiActions()` | Parse Explorer `switch` actions from live `modules/explorer/api.php` |
| `itmDocCollectIdfApiEndpoints()` | List IDF API files with purpose blurbs |
| `itmDocProjectJsonEndpoints()` | Curated non-import AJAX endpoints |
| `itmDocSwitchPortApiEndpoints()` | Switch Port Manager (`includes/get_ports.php`, `includes/update_port.php`) response contracts |
| `itmDocPasswordsApiActions()` / `itmDocNotesAjaxActions()` / `itmDocTodoAjaxActions()` | Module-specific action matrices |
| `itmDocHotelBookingDistributionApiActions()` / `itmDocHotelBookingDistributionWireFormats()` | Partner distribution router actions and wire formats |
| `itmDocLiveChatApiActions()` / `itmDocNotificationsApiEndpoints()` | Live Chat and header bell JSON APIs |
| `itmDocIntegrationWebhookEvents()` | Outbound webhook event catalog |
| `itmDocCollectApiExamples()` | Scan every `api-examples/*.php` file (title/category/purpose table in `api.php`) |
| `itmDocSelectOptionsAllowedTables()` | Load allowed quick-add tables from `includes/itm_select_options_policy.php` (includes `license_types`) |
| `itmDocApiRateLimitTiers()` | Tier → hourly limit table for API key documentation |

**Verify after API doc changes:**

```bash
php -l scripts/api.php
php scripts/run_tests.php --filter ApiFunctionsTest
```

Open `scripts/api.php` in the browser and confirm Explorer, IDF, and import tables render.

#### JSON import UPDATE semantics (`itm_handle_json_table_import()`)

Shared handler in `config/config.php` (and module-specific paths such as `modules/employees/index.php` for the dedicated employee import UI).

| Topic | Behaviour |
|-------|-----------|
| **UPDATE scope** | Only columns present in the import header row (or auto-derived during normalization with a resolved non-`NULL` value) are written on existing rows. Omitted columns keep their stored values. |
| **INSERT scope** | Unchanged — missing columns still receive defaults/auto-derived values as before. |
| **Auto-derived fields** | Examples: resolved FK IDs, auto-created department/position rows, employees `personal_email` reclassification from a work-email column, derived `display_name`. |
| **Empty rows** | Rows with no non-blank, non-`null` cells after trim are skipped with no DB mutation. |
| **No-op UPDATE** | Existing row matched by `id` but no writable import columns → increments **`skipped`**, not **`updated`**. |
| **Response JSON** | `{"ok":true,"inserted":N,"updated":N,"skipped":N,"failed":N}` — `ok` is false when `failed > 0` and no rows were inserted/updated/skipped. |

**Regression (CLI-only, exit non-zero on failure):**

```bash
php scripts/repro_generic_dataloss.php
php scripts/repro_employee_dataloss.php
php scripts/verify_json_import_validation.php
```

Catalog rows in `scripts/scripts.php` are **CLI-only** (no browser runner links). The scripts index requires administrator login in the browser.

#### Switch Port Manager AJAX (`includes/get_ports.php`, `includes/update_port.php`)

Equipment Switch Port Manager tiles call these shared endpoints (not module-local PHP under `modules/switch_ports/`).

| Endpoint | Role |
|----------|------|
| **`includes/get_ports.php`** | POST `switch_id` + CSRF. Seeds missing `switch_ports` rows for RJ45/SFP capacity, then returns ports and lookup metadata (statuses, colors, VLANs, IDF/rack/location options). Success: `{"success":true,…}` via `itm_api_json_response()`. Tenant `company_id` from session only — ignore client-supplied company ids; missing session tenant → HTTP `403`. |
| **`includes/update_port.php`** | POST port `id`, `switch_id`, field updates + CSRF. Tenant-scoped UPDATE on `switch_ports`; To IDF auto-sync in a transaction when `management_id` exists. Zero-row updates return HTTP `404` before IDF sync (manual check — not `itm_api_mutation_requires_rows()`, which exits immediately on success). |

Shared helpers: **`includes/switch_port_api_helpers.php`** (lookup maps, VLAN list). Prepared reads use **`itm_mysqli_stmt_fetch_assoc()`** / **`itm_mysqli_stmt_fetch_all_assoc()`** (mysqlnd fallback). Entry scripts use **`includes/itm_script_entry_guard.php`** and **`includes/itm_api_json_response.php`**.

Documented in **`scripts/api.php`** (Switch Port Manager API section) and module notes: **`modules/equipment/AGENT_NOTES.md`**, **`modules/switch_ports/AGENT_NOTES.md`**.

**Verify after switch-port endpoint changes:**

```bash
php -l includes/get_ports.php
php -l includes/update_port.php
php -l includes/switch_port_api_helpers.php
php scripts/check_sql_injection_coverage.php
php scripts/run_tests.php --filter ApiFunctionsTest
php scripts/verify_update_port_zero_row.php
php scripts/verify_metadata_column_cache.php
php scripts/idfs_sync_human_test.php
php scripts/auth_register_reset_human_test.php
```

**`verify_update_port_zero_row.php`:** asserts HTTP `404` on zero-row `update_port.php` before IDF auto-sync. Creates disposable probe equipment + `switch_ports` row when the tenant has none (transaction-wrapped). Subprocess seeds `$_SESSION['company_id']` before `config.php`, stubs `itm_api_json_response()` to capture HTTP status, sets `$company_id` before including `update_port.php`, uses CLI `php.exe`, and decodes the JSON status wrapper after CLI `header()` output lines. Optional env: `ITM_TEST_COMPANY_ID` (default `1`).

**`verify_metadata_column_cache.php`:** asserts table-level caching in `itm_table_has_column()` / `itm_table_column_is_nullable()` (`includes/bootstrap_helpers.php`). Cold batch on `switch_ports` (15 checks matching `update_port.php`) expects schema `Questions` delta 1–2; warm repeat expects schema delta 0 (measurement excludes trailing `SHOW STATUS`). Optional env: `ITM_META_CACHE_TABLE` (default `switch_ports`).

**`idfs_sync_human_test.php`:** after Admin login, POSTs to `index.php` to align session `company_id` with `ITM_COMPANY_ID` (login otherwise pre-selects the first active company alphabetically). Company-selection GET resolves `Location` redirects manually (open_basedir-safe; does not rely on `CURLOPT_FOLLOWLOCATION`). When `ITM_COMPANY_ID` / `ITM_IDF_ID` do not match an active IDF row, resolves the first active IDF in the database.

**`auth_register_reset_human_test.php`:** invite → register → login → reset-password human-style regression without a browser. Asserts `mysqli_stmt_bind_param` contracts on `login.php`, `forgot-password.php`, and `reset-password.php`; verifies tenant-scoped **Active** `employment_status_id` on registration (companies 1–2 by default). **Mutates DB:** disposable invitations and `script-*` employees; teardown via `itm_script_test_employee_register_teardown()`. Optional: `--company=2`. Browser: `scripts/auth_register_reset_human_test.php?company=2` (uses `$_GET['company']` when `$argv` is unavailable).

**`verify_password_reset_flow.php`:** store/lookup/complete reset tokens using `includes/itm_password_reset.php` (MySQL `DATE_ADD` expiry, legacy plain-token fallback). Uses a disposable script-test employee.

#### API tier rate-limit regression (`apitest_tier_*.php`)

| Script | Purpose |
|--------|---------|
| `php scripts/apitest_tier_free.php` | Disposable **Free** tier row (empty `api_key`): unlimited status, in-process session resolve via disposable test user (`itm_script_with_test_session_context()`), repeated consumes allowed. **Browser + CLI** (browser: Admin login). HTTP probe uses isolated test-user cookie — not Admin. |
| `php scripts/apitest_tier_basic.php` | Disposable **Basic** tier row seeded at `limit - 1`: next consume succeeds, following consume is blocked. **Browser + CLI** (browser: Admin login). HTTP probe requires `api_key`. |

Shared helpers: `scripts/lib/itm_api_tier_test_helpers.php` (disposable `company_id`/`employee_id` slots, browser URL with optional `api_key`, HTTP probe). Slot employees (`apitest-user-{id}`) are created with prepared INSERTs; helpers clear stale `@app_employee_id` audit session vars before `employees` / `ui_configuration` mutations so `audit_logs_ibfk_employee` does not reject seeding. Session resolve/consume tests use **`itm_script_with_test_session_context()`** (disposable test user — not Admin). HTTP probes use **`itm_script_publish_isolated_http_session()`**. Entry: **`scripts/lib/itm_script_regression_entry.php`** (browser + CLI; Admin required in browser). Requires MySQL (`itmanagement` schema). Catalog: `scripts/scripts.php`.

**Free** tier prints a session probe URL (`scripts/api.php?rate_limit=1` without `api_key`). The Free apitest publishes an **isolated** disposable test-user `PHPSESSID` (`itm_script_publish_isolated_http_session()` via `itm_apitest_publish_http_session()`) **before any script output** so the curl HTTP probe can pass without an API key when Apache is running — it does **not** reuse or overwrite the signed-in Admin browser session. **Paid** tiers print `…&api_key=…`. Probe returns JSON without a PHP session redirect (`ITM_API_RATE_LIMIT_PROBE`). Disposable rows remain until the next apitest run for that slot.

**Verify after rate-limit helper or tier cap changes:**

```bash
php -l scripts/apitest_tier_free.php
php -l scripts/apitest_tier_basic.php
php scripts/apitest_tier_free.php
php scripts/apitest_tier_basic.php
```

#### API v2 partner gateway (`verify_api_v2.php`, `openapi.php`)

| Script | Purpose |
|--------|---------|
| `php scripts/verify_api_v2.php` | Regression: `api_key_scopes` table, PATH_INFO route registry, scope grants, ticket list/create handlers, OpenAPI builder. Disposable Basic-tier row via `itm_apitest_disposable_user_id(46)`. **Browser + CLI** (browser: Admin). |
| `php scripts/openapi.php` | Emit OpenAPI 3.0 JSON from `itm_api_v2_route_registry()`. **No login** (`ITM_SCRIPT_NO_AUTH`). Browser: `scripts/openapi.php?format=json`. |

Canonical doc: `docs/API_V2.md`. Router: `modules/api_v2/router.php`. Examples: `api-examples/api_v2_*.php`. PHPUnit: `php scripts/run_tests.php --filter ApiV2`.

**Verify after API v2 router, scope, or handler changes:**

```bash
php -l includes/itm_api_v2.php
php -l includes/itm_api_v2_scopes.php
php -l modules/api_v2/router.php
php scripts/verify_api_v2.php
php scripts/run_tests.php --filter ApiV2
```

#### 2. Browser scripts (`scripts/*.php` opened in the browser)

* **Special Case: `scripts/health.php` (MANDATORY):** This file is a **shell bootstrap** (not a PHP entry script) used to provision an automated health-check endpoint on deployment hosts. Do not add navigation links, headers, or PHP logic that changes its output format. **`perform_audit.php`** excludes it — bare `php scripts/health.php` only echoes shell commands and is not a regression signal.
* **Back link (required):** Every HTML report must show **← Scripts index** at the top, linking to `scripts/scripts.php` (relative `scripts.php` from `scripts/`).
  * Use `scripts/lib/script_browser_nav.php`: `require_once …/script_browser_nav.php`; then `itm_script_browser_nav_echo()`.
  * Plain-text-in-`<pre>` audits: use `scripts/lib/script_cli_output.php` (`itm_script_output_begin()`), which includes the same nav bar and opens a single `<pre>` for the log.
  * **Coloured `<pre>` regressions:** Human-flow runners (`explorer_human_test.php`, `verify_audit_logs_disclosure.php`, `apitest_tier_*.php`, …) keep the full log inside that `<pre>`. Use `itm_script_output_nl()` for line breaks and `itm_script_format_status_line()` / `colorText()` for `[PASS]` / `[FAIL]` / `[INFO]` colouring. Do **not** call `itm_script_output_close_pre()` for HTML intros unless the script reopens `<pre>` for the log body. **`scripts/apply*.php`** is the exception — named dry-run/apply lists use real `\n` inside `<pre>` (see **Repo-writing maintenance** below).
  * **No duplicate nav (mandatory):** `itm_script_output_begin()` already renders **← Scripts index** once. Do **not** call `itm_script_browser_nav_echo()` again in the same browser response. Custom full-page HTML (no `itm_script_output_begin()`) must **not** append bare `itm_script_output_end()` after `</html>` — that text is echoed outside PHP. Scripts that use `itm_script_output_begin()` must call `itm_script_output_end()` **inside** PHP (do not close with `?>` before `output_end`). Static gate: `php scripts/check_script_browser_nav_duplicate.php` (browser + CLI; also flags stray `output_end` after `</html>` or after `?>`).
* **Human-readable results:** Browser output must explain findings in plain language (not only internal codes). Example: write “Duplicate dropdown option” rather than only `duplicate_dropdown_risk`. Include a short “what to do next” when useful.
* **Line-breaking prevention for tables (nowrap standard):** To guarantee readability and maintain professional UI appearance in admin/report tool dashboards (e.g., `crud_tables.php`, `crud_titles.php`, `crud_actions.php`), data rows must not wrap arbitrarily. Implement this by applying `white-space: nowrap;` to table header and body cells (`thead th` and `tbody td`), and wrap the table structure in a container set to `overflow-x: auto;` (such as the `.wrap` container class) to facilitate horizontal scrolling for overflowing data without distorting column structures.

#### Link creation rules (browser scripts — mandatory)

All outbound links in HTML script output must use helpers from **`scripts/lib/script_browser_nav.php`**. Do **not** hand-build `<a href="…">` with `BASE_URL`, `itm_script_module_index_url()`, or phpMyAdmin URLs.

| What appears in the report | Create a link? | How (browser) | Example |
|---------------------------|----------------|---------------|---------|
| **← Scripts index** | Always | `itm_script_browser_nav_echo()` | `scripts.php` (relative) |
| **Module folder** (`floor_plans`, `catalogs`, …) | Always | `itm_script_format_module_link('floor_plans')` or `itm_script_format_module_path_link('modules/catalogs/')` | `../modules/floor_plans/index.php` |
| **Database table name** (`catalogs`, `floor_plan_folders`, …) | **Only if** `modules/<table>/index.php` exists | `itm_script_format_table_link($tableName)` | `catalogs` → module link; `floor_plan_folders` → plain text only |
| **Missing module table** (`fields_missing.php` footer) | Always (expected path) | `itm_script_format_table_link($tableName, '', true)` | `floor_plan_folders` → link to `../modules/floor_plan_folders/index.php` even when the folder is absent |
| **View/edit field parity** (`check_crud_view_edit_field_parity.php`) | Per-scope reference gap line | `itm_crud_view_edit_field_parity_format_module_ref($moduleSlug)` | `expenses` → link to `../modules/expenses/` (folder, new tab); plain line `{slug}.{column}: schema column not referenced in {scope}` |
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
* **Windows Laragon (mandatory for tests):** `D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe` — always use this full path when running scripts locally; in **PowerShell** prefix with **`&`**; list the exact shell command in PR test plans (see **`AGENTS.md` → Setup & Debugging → PHP CLI tests**).
* **`PHP_BINARY` for sub-processes:** When a script needs to execute another PHP script, prefer using the **`PHP_BINARY`** constant to ensure the same PHP version is used.
* **Repo-writing maintenance (`scripts/apply*.php` and peers):** **Browser + CLI** via `scripts/lib/itm_apply_script_bootstrap.php`. Default run is always **dry-run** (no writes; browser dry-run needs a signed-in session only). Writes only with CLI `--apply` or browser `?apply=1` (**Admin** session required for browser apply). Catalog shows **Browser** + **CLI** badges only (dry-run is described in **How to use**, not a separate badge).
* **Static check scripts** (`check_*` audits): **Browser + CLI** via `scripts/lib/itm_script_access_helpers.php` → `itm_check_script_begin_browser_admin()` (Administrator in browser).
* **Session-mock harnesses** (`test_ajax.php`, `test_edit.php`): **Browser + CLI** — browser Admin form (PHPSESSID + title/note id) or CLI positional args (`<PHPSESSID>`, title, note id for edit). CLI argv harness excluded from **`perform_audit.php`** (same contract as CSRF coverage skip).
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
| `scripts/lib/itm_apply_script_bootstrap.php` | Shared bootstrap for `scripts/apply*.php` and repo/DB writers with dry-run default: browser + CLI, `--apply` / `?apply=1`, `itm_script_require_admin_script_or_exit()` for browser apply only (function-local `$conn` from `config.php` — returned as `$boot['conn']`; never `require config.php` before bootstrap), `itm_apply_script_echo_list()` |
| `scripts/lib/itm_script_access_helpers.php` | `itm_check_script_begin_browser_admin()` for static `check_*` audits; `itm_script_echo_cli_only_page()` for true CLI-only instruction pages |
| `scripts/lib/itm_script_bootstrap.php` | Global `scripts/*` contract (loaded from `config.php`): disposable test-session rejection, `itm_script_with_test_session_context()`, isolated HTTP probe sessions, optional Admin browser gate |
| `scripts/lib/itm_script_cli_entry.php` | Alias for `itm_script_regression_entry.php` |
| `scripts/lib/itm_codacy_xss_echo_audit.php` | Codacy XSS echo pattern matchers for `check_codacy_xss_echo.php` |
| `scripts/lib/itm_manual_sql_string_audit.php` | Manual SQL string pattern matchers for `check_manual_sql_string.php` |
| `scripts/lib/itm_not_operator_audit.php` | Unary `!` on `$variable` pattern matchers for `check_not_operator.php` [Warning] (Codacy-style; always exit 0; excludes `!is_*()` / `!==`) |
| `scripts/lib/itm_mojibake_audit.php` | UTF-8 / mojibake scan + repair helpers for `verify_source_utf8_mojibake.php` and `fix_source_utf8_mojibake.php` |
| `scripts/lib/itm_script_regression_entry.php` | Browser + CLI regressions: `ITM_CLI_SCRIPT` on CLI only, Admin gate in browser, `config.php` at file scope |

#### Equipment-type façade modules (`modules/is_*`) and clear-table tests

Canonical equipment-type wrappers live under **`modules/is_*`** (for example `is_switch`, `is_server`, `is_workstation`). They delegate to `modules/equipment/` and must **not** be deleted by maintenance scripts.

| Script | Role |
|--------|------|
| `scripts/lib/equipment_type_modules.php` | Shared allowlist + `itm_remove_equipment_regression_test_module_dirs()` / `itm_ensure_canonical_equipment_type_modules()` |
| `scripts/ensure_equipment_type_modules.php` | Verify or recreate missing canonical `modules/is_*/index.php` wrappers (Browser + CLI; dry-run lists missing, `--apply` scaffolds) |
| `scripts/cleanup_equipment_test_module_artifacts.php` | Cleanup utility: remove test `equipment_types` rows (incl. `MBQA-equipment_types-…`), ITM test companies, junk `is_*_itm_eqdct_*` / `is_mbqa_equipment_types_*` folders, sidebar prefs, then re-ensure canonical façades. Browser + CLI; **dry-run** preview via `itm_preview_equipment_test_module_artifacts_cleanup()` |
| `scripts/equipment_delete_clear_table_test.php` | DB regression for equipment `clear_table` + transactional single delete (use type names **`Switch`** / **`Server`**, not suffixed names) |
| `php scripts/debug_equipment_create_rollback_errno.php` | Debug — deliberate failing equipment INSERT in a transaction; prints `mysqli_errno()` / `mysqli_error()` and `itm_format_db_constraint_error()` **before vs after** `mysqli_rollback()`. Use when manual Server create shows the generic “Review the required fields” message instead of a column-specific DB error. CLI `--company_id=1`; browser `?company_id=1`. |
| `scripts/employees_delete_clear_table_test.php` | DB regression for employees `clear_table` soft-delete + detach |
| `scripts/check_equipment_clear_table_delete.php` | Static guard for equipment clear-table soft-delete (`equipment_delete_record`, transaction, `itm_crud_build_soft_delete_sql`). Browser + CLI. |
| `scripts/check_employees_clear_table_transaction.php` | Static guard for employees clear-table soft-delete helper. Browser + CLI. Run after employees `clear_table` changes. |

**Why tests must not invent new `is_*` folder names:** inserting `equipment_types` named like `Switch itm_eqdct_*` or QA tags `MBQA-equipment_types-…` triggers `itm_ensure_equipment_type_module_scaffold()` in `includes/ui_config.php` and pollutes the sidebar. In the browser, **`module_browser_qa_runner.php`** now runs **`module_clean_tests_qa_runner.php` silently before and after** **Run QA**; for other equipment DB tests, run `php scripts/cleanup_equipment_test_module_artifacts.php` manually.

#### Standard flattened CRUD template (`modules/manufacturers/`)

Simple reference modules and auto-discovered DB tables use **copied** PHP from `modules/manufacturers/` (see `itm_materialize_standard_crud_module_files()` in `includes/ui_config.php`).

| Rule | Detail |
|------|--------|
| **Allowed delegate** | Only files inside `modules/manufacturers/` |
| **Forbidden** | `require __DIR__ . '/../manufacturers/…'` in any other module folder |
| **Auto-scaffold** | `itm_auto_create_module_scaffold($table)` copies template files when a DB table has no `modules/{table}/index.php` |
| **Sidebar label** | Newly scaffolded tables show **⚠️** in discovery (`itm_sidebar_auto_scaffolded_module_emoji()`) |
| **QA cleanup** | `module_clean_tests_qa_runner.php` removes legacy thin delegate folders via `itm_remove_standard_crud_scaffold_module_dirs()` (never deletes `modules/manufacturers/`) |
| **Refresh materialized module** | CLI after template edits: load app once, then `itm_materialize_standard_crud_module_files('note_labels', true)` (or other slug) |
| **Static guard** | `php scripts/check_standard_crud_delegate_requires.php` — fails if any `modules/*/` PHP file (except `manufacturers/`) contains `require … ../manufacturers/` |

Materialized examples: `modules/note_labels/`, `modules/modules_registry/`.

#### Smoke tests (CI — `scripts/smoke_test.sh`)

> **Pre-PR mandatory (agents — hard fail):** before **`git push`** and **`gh pr create`**, run **all four** jobs below locally in order — same commands as `.github/workflows/smoke.yml`. See **`AGENTS.md`** step **8a**. Do not open a PR until **smoke**, **database-import**, **tier2**, and **phpunit** all exit `0`.

GitHub Actions (`.github/workflows/smoke.yml`) runs four jobs on push/PR: **smoke**, **database-import**, **tier2**, and **phpunit**.

| Job | Command | Purpose |
|-----|---------|---------|
| **smoke** | `bash scripts/smoke_test.sh` | PHP syntax lint + CSRF + SQLi + FK label search coverage audits (no MySQL) |
| **database-import** | `bash scripts/verify_database_sql_import.sh` then `php scripts/verify_crud_fk_label_search.php` | Full `db/` import on MySQL 8.0 service (`MYSQL_PORT=3306` in workflow — GHA maps `3306:3306`; local Dunebox uses `MYSQL_PORT=3307` or `.env`); asserts live table count matches `CREATE TABLE` entries in `db/01_schema.sql` (derived at runtime — `grep -c '^CREATE TABLE' db/01_schema.sql`); runtime FK label search regression |
| **tier2** | `php scripts/run_tier2_checks.php` | Tier 2 static batch from `SCRIPTS_TEST_MATRIX.md` (`check_*` + `list_raw_columns.php`; no MySQL) |
| **phpunit** | `ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php` | PHPUnit unit suite without live-database tests (DB integration cases skipped in CI) |

**smoke** job steps only:

| Step | Command | Purpose |
|------|---------|---------|
| 1 | `php -l` on every `*.php` | Syntax lint |
| 2 | `php scripts/check_csrf_coverage.php` | POST handlers / forms have CSRF |
| 3 | `php scripts/check_sql_injection_coverage.php` | SQLi coverage audit |
| 4 | `php scripts/check_fk_label_search_coverage.php` | FK label search static coverage (100% gate). Browser: `scripts/check_fk_label_search_coverage.php` (Administrator session). |

**database-import** job also runs `php scripts/verify_crud_fk_label_search.php` after import (requires MySQL).

Local full import (requires MySQL, password `itmanagement`): `bash scripts/verify_database_sql_import.sh` — same entry point as CI **database-import** step 1. **Port:** CI sets `MYSQL_PORT=3306`; Dunebox default in `import_database_split.sh` is **3307** (no extra env). Laragon or other MySQL on **3306**: `MYSQL_PORT=3306 bash scripts/verify_database_sql_import.sh`. Direct split import: `bash scripts/import_database_split.sh` (see `db/AGENT_NOTES.md`). Then run `php scripts/verify_crud_fk_label_search.php` for runtime FK label search regression.

**Database import (`db/`):** canonical files under `db/` (`01_schema.sql`, `02_data.sql`, `03_triggers.sql`). Import in **one MySQL session** in order **01 → 02 → 03** (`bash scripts/import_database_split.sh`) so `@replicate_source_company_id` persists and audit triggers load after seed data. Details: `db/AGENT_NOTES.md`.

| Script | Purpose |
|--------|---------|
| `php scripts/verify_database_schema.php` | Compare `CREATE TABLE` names in `db/01_schema.sql` with live `information_schema` (catches partial imports). |
| `php scripts/migrate.php --status` | Probes **live database** for every `db/migrations/*.sql` file (Applied vs Pending). `schema_migrations` is audit/history only (checksum drift fails). Browser `?run=1` HTML table includes per-row **Open SQL** (`?run=1&sql={filename}`, new tab) and **Delete** 🗑️ (Admin POST + JS confirm — removes file from disk). Browser + CLI (Admin for apply/delete). |
| `php scripts/migrate.php --apply` | Runs SQL only when the live probe failed; records schema-satisfied migrations without re-executing destructive files. Stamps `filename` + SHA-256 `checksum`. Helpers: `includes/itm_database_migrations.php`. |
| `php scripts/verify_schema_migrations_module.php` | Static + DB checks for admin read-only `modules/schema_migrations/` (no bulk/clear/sample-data; admin gate; wrappers redirect). Browser `?run=1` + CLI. |
| `php scripts/verify_db_migrations.php` | Glob every `db/migrations/*.sql` file (natural sort) and probe live DB state (Applied / Superseded / Not applied / DML only). Generic probes parse `CREATE TABLE` / `CREATE TRIGGER` from each file; a few DML/share files use custom handlers. Browser + CLI (Admin). `--json` / `?format=json`. Lib: `scripts/lib/itm_verify_db_migrations_report.php`. |
| `php scripts/verify_bigint_table_review.php` | Live row counts, max `id`/`record_id`/`module_id`, column types, and AUTO_INCREMENT for BIGINT migration candidates (`audit_logs`, `modules_registry`, `company_module_access`, `company_module_share`, `employee_sidebar_preferences`, `system_access`). Browser + CLI (Administrator); browser landing `?run=1`; coloured `[PASS]`/`[FAIL]` report via `itm_script_output_begin()` (same shell as `verify_db_migrations.php`). Includes static **300 staff × 5 companies** scale projection (compare with live counts). Pair with `db/migrations/audit_logs_bigint.sql`. |

### BIGINT migration review (`verify_bigint_table_review.php`)

| Script | Purpose |
|--------|---------|
| `php scripts/verify_bigint_table_review.php` | Administrator browser + CLI probe before/after `db/migrations/audit_logs_bigint.sql`: per-table row counts and max IDs, `information_schema` column types for `id` / `record_id` / `module_id`, AUTO_INCREMENT values, guidance lines, and static **300 staff × 5 companies** scale projection (HTML tables in browser + coloured `<pre>` section). Browser: `scripts/verify_bigint_table_review.php?run=1` (usage landing, ← Scripts index, HTML summary table + coloured `<pre>` log). CLI: `php scripts/verify_bigint_table_review.php`. Exit `1` when a count/type probe fails. **Not** a no-auth script — use `count_db_tables.php` for login-free table totals only. |

Catalog: `scripts/scripts.php`.

Other scripts (`check_index_table_compliance.php`, `check_company_id_ui_column.php`, `check_crud_has_company_from_field_columns.php`, `check_crud_boolean_cell_display.php`, `check_ui_configuration_coverage.php`, `check_display_field_columns_search.php`, `check_crud_view_edit_field_parity.php`, `check_ui_action_emoji.php`, `check_pagination_emoji.php`, `check_module_decorated_links.php`, `check_crud_audit_soft_delete.php`, employees/equipment clear-table guards, DB regression tests) are **not** part of smoke — run them manually when the change scope requires it (see `scripts/scripts.php`).

**Tier 2 batch (pre-merge static cluster):** run every Tier 2 script from `SCRIPTS_TEST_MATRIX.md` in one pass (`check_*` + `list_raw_columns.php`):

```bash
php scripts/run_tier2_checks.php
php scripts/run_tier2_checks.php --continue
php scripts/run_tier2_checks.php --only=check_ui_action_emoji.php,check_audit_logs_coverage.php
```

Browser menu: `scripts/run_tier2_checks.php` → **Run Tier 2 batch**. Optional `PHP_BIN=/path/to/php.exe` (Windows Laragon). Parses `SCRIPTS_TEST_MATRIX.md` for Tier 2 rows (any `*.php`, including `list_raw_columns.php`); falls back to a built-in list when the matrix is missing. Does not mutate the database.

#### Scaffold audit columns + soft-delete

| Script | Purpose |
|--------|---------|
| `php scripts/verify_audit_columns.php` | Live-DB gate for mandatory audit/soft-delete columns (including `live_chat_typing`). `live_chat_messages` / `live_chat_typing` stay private-data exempt from `audit_logs` triggers. |
| `php scripts/apply_crud_audit_soft_delete.php` | **Browser + CLI.** Default run is always **dry-run**; writes only with CLI `--apply` or browser `?apply=1` (Admin). After the count summary, prints named module lists (inventory from `docs/list_soft-delete.txt`, status-driven skips, missing dirs, needing patch, already compliant) using real newlines so browser `<pre>` stays readable. Patches scaffold modules (list hide meta, view show meta, soft-delete SQL, stamps). Idempotent when modules already comply. Skips status-driven slugs (`employees`, `equipment`, `patches_updates`, `tickets`). |
| `php scripts/check_crud_audit_soft_delete.php` | Static gate: list hide helper / `$viewColumns` (or bespoke `itm_crud_render_audit_cell_value` on `view.php`) / `deleted_at IS NULL` / soft-delete helper for in-scope modules (including status-driven `employees`, `equipment`, `patches_updates`, `tickets`). |
| `php scripts/check_audit_logs_coverage.php` | Static gate: every `db/01_schema.sql` table has `trg_*_audit_*` in `db/03_triggers.sql` or is exempt (`audit_logs`, private-data tables, system/derived `schema_migrations` + `search_index` per `AGENTS.md`). Exit `2` on module FAIL or schema gaps. |
| Inventory | `docs/list_soft-delete.txt` (in scope), `docs/list_bespoke_UI.txt` (deferred). Helpers: `includes/itm_crud_audit_fields.php` (soft-delete also sets `active=0`; status-driven forms use hidden `active=1`). |

Optional DB regression (requires MySQL): `php scripts/employees_delete_clear_table_test.php`, `php scripts/equipment_delete_clear_table_test.php`.

Module seed expansion in `db/02_data.sql` (repo write, no DB mutation): `php scripts/apply_module_sample_data_seed.php --module=<module_name> [--sample=name[:emoji] ...]` (dry-run default; `--apply` / `?apply=1` writes `db/02_data.sql` and refreshes `db/02_data_sample.sql`). Parses single-row and multi-row `INSERT … VALUES` blocks; adds one row per seeded `company_id` when a sample value is missing for that tenant. **Mirror mode:** when `db/` uses `INSERT … SELECT N, cols FROM table WHERE company_id = 1` (e.g. `knowledge_base`), new samples append only to the source company VALUES block — other tenants replicate on import. Use `--sample=title:content` for title/content tables. Browser dry-run: `scripts/apply_module_sample_data_seed.php?module=idf_device_type`; apply (Admin): `...?module=idf_device_type&apply=1`. Error paths use `itm_seed_fwrite_stderr()` (not raw `fwrite(STDERR)` — undefined in browser SAPI).

| Script | Purpose |
|--------|---------|
| `php scripts/extract_02_data_sample.php` | **Browser + CLI.** Dry-run default. Requires MySQL. Writes `db/02_data_sample.sql` from company `1` rows in `db/02_data.sql`, live MySQL backfill, and minimal synthesis so **every** tenant-scoped table has at least one template. `--apply` / `?apply=1` (Admin). |
| `php scripts/check_02_data_sample_coverage.php` | Static gate: every tenant-scoped table (schema `company_id`, minus `itm_sample_sql_exempt_tables()` in `includes/itm_sample_sql_export.php`) has ≥1 company `1` marker row in `db/02_data_sample.sql`. Exit `1` when missing. Exempt tables include junction/matrix rows and child/runtime tables (finance lines, live chat, ticket activity, webmail) — not the same set as the optional PHP gate apply script (~77 legacy `COUNT` blocks). |
| `php scripts/dedupe_02_data_per_company_inserts.php` | **Browser + CLI.** Lists/removes redundant companies `2–5` single-row INSERTs when `@replicate_source_company_id` replication exists. Dry-run default; `--apply` edits `db/02_data.sql`. |
| `php scripts/verify_sample_data_seed.php` | **Browser + CLI.** Regression: seeds arbitrary disposable companies from `db/02_data_sample.sql` (`workstation_ram`, FK parent chain, duplicate skip, random fallback, `backup_tape_log`, `switch_ports`, `equipment`). Browser (Admin): `scripts/verify_sample_data_seed.php`. CLI: `php scripts/verify_sample_data_seed.php`. |
| `php scripts/verify_finance_sample_data_seed.php` | **CLI.** Regression: Add sample data for finance tables (`expenses`, `bills`, `invoices`, lookups, budgets) on a disposable company; asserts `currency_code` stays within `char(3)`, soft-deleted-only gate behaviour (e.g. `tax_rates`), and finance slugs map to sidebar section `finance` in `includes/ui_config.php`. Run when changing finance templates/defaults or sidebar Finance section. |
| `php scripts/check_crud_sample_data_live_row_gate.php` | **CLI.** Static gate: finance module `index.php` files must use `itm_seed_tenant_row_count()` for the **Add sample data** empty check (not raw `COUNT(*)` on `company_id` only). Row templates remain in **`db/02_data_sample.sql`** (`itm_seed_table_from_database_sql()`). Exit `1` on drift. |
| `php scripts/apply_crud_sample_data_live_row_gate.php` | **CLI/browser.** Optional PHP maintenance only — does **not** edit `db/`. Replaces legacy sample-data `COUNT(*)` gates with `itm_seed_tenant_row_count()`. Dry-run default; `--apply`; optional `--finance-only`. Many modules already gate via `itm_crud_append_not_deleted_predicate()` and are skipped. |
| `php scripts/debug_sample_data_soft_delete_gate.php` | **CLI/browser (Admin).** Audit **Add sample data** gates vs soft-delete list queries: static drift (`raw_count` vs `itm_seed_tenant_row_count()`), optional live repro per `company_id` (`live=0`, `raw>0`). Browser: HTML table with columns Status, Module, Table, Gate, Raw, Live, **SQL sample** (`db/02_data_sample.sql` template row count), Soft, Notes (no `<pre>` log). Examples: `php scripts/debug_sample_data_soft_delete_gate.php --only-repro --company=4`; [debug_sample_data_soft_delete_gate.php?run=1&company=4&only_repro=1](http://localhost/it-management/scripts/debug_sample_data_soft_delete_gate.php?run=1&company=4&only_repro=1). Exit `1` when drift/REPRO rows exist. Lib: `scripts/lib/itm_sample_data_soft_delete_gate_audit.php`. |
| `php scripts/list_raw_columns.php` | **CLI/browser (Admin).** Audit list/view FK columns that still render raw numeric ids. Detects shared `$GLOBALS['fkMap'][$field]`, bespoke resolvers (`cr_fk_label_by_id`, `cr_username_for_employee_id`, inline SQL in `cr_render_cell_value()`), list-template `isset($fkMap[$f])` labels, and **JOIN + $row** bespoke patterns. Skips columns removed from `$uiColumns` / `$listColumns` (vault hidden fields, `cr_is_hidden_employee_field`). Scans **all** flattened CRUD modules. Default: **RAW** / **REPRO** / **SKIP** only; `--all` includes compliant rows (handlers: `fkMap`, `join_row`, `list_fkmap`, `inline_sql`, `bespoke`). Optional live **REPRO** with `company=`. Examples: `php scripts/list_raw_columns.php --only-raw`; [list_raw_columns.php?run=1](http://localhost/it-management/scripts/list_raw_columns.php?run=1). Exit `1` when RAW/REPRO rows exist. **Tier 2:** included in `run_tier2_checks.php`. Lib: `scripts/lib/itm_raw_fk_column_display_audit.php`. Fix pattern: `modules/license_management/index.php`. Bulk repair: `php scripts/apply_crud_fk_label_display.php` then `--apply`. |
| `php scripts/list_date_display_formats.php` | **CLI/browser (Admin).** Per-module **display** date audit: **OK** = UK `dd/mmm/yyyy` helpers or `module_pass`; **WARN** = raw ISO list/view echo or non-UK `date()` on output; **SKIP** (`module_skip`) = `reports`, `ops_report`, `settings`, `backup_tape_log`, `birthdays`, `resignations`, `calendar`, `explorer`, `hotel*`, `booking_*` (does not scan repo `booking/` guest portal). Native `type="date"` inputs skipped unless `--include-inputs`. Default: WARN + `module_pass` + `module_skip` rows; `--module=` filter; `--all` line-level OK; `--no-show-pass` / `--no-show-skips`. Detection only. Examples: `php scripts/list_date_display_formats.php --module=calendar`; [list_date_display_formats.php?run=1](http://localhost/it-management/scripts/list_date_display_formats.php?run=1). Exit `1` when WARN rows exist. Lib: `scripts/lib/itm_module_date_format_display_audit.php`. |
| `php scripts/apply_crud_fk_label_display.php` | **CLI/browser (Admin).** One-time/maintenance: add canonical `$GLOBALS['fkMap']` label branch to flattened CRUD `cr_render_cell_value()` (plus `cr_fk_label_by_id()` wrapper when missing). **Default = dry-run**; writes with CLI `--apply` or browser `?apply=1`. Skips bespoke modules refactored manually (`registration_invitations`, `role_*`, `employee_assignment_history`). Idempotent. Examples: [apply_crud_fk_label_display.php?run=1](http://localhost/it-management/scripts/apply_crud_fk_label_display.php?run=1). Verify: `php scripts/list_raw_columns.php`. |

#### PHPUnit test runner (`scripts/run_tests.php`)

Central runner for the suite under `phpunit/tests/Unit/` using `phpunit/phpunit.phar` and `phpunit/phpunit.xml`. Catalog row: **`scripts/scripts.php`**.

**EGPCS and .env Support (mandatory):** The runner executes the PHPUnit process with `-d variables_order=EGPCS` to ensure that environment settings from local `.env` files are correctly parsed and loaded. This prevents database connection failures in environments where the system-wide PHP configuration omits the `E` flag from `variables_order`.

**CLI Argument Forwarding:** Additional command-line arguments (such as `--filter <pattern>` or single test files) are automatically forwarded to the underlying PHPUnit CLI binary, enabling targeted test executions directly through the runner script. Example: `php scripts/run_tests.php --filter CompanyModuleAccessDiscoveryTest`.

**Scaffolding cleanup:** Auto-scaffolding during PHPUnit (for example `CompanyModuleAccessDiscoveryTest` with `enable_auto_scaffolding`) must not leave probe folders under `modules/`. Tests call `itm_sidebar_discovery_probe_cleanup()` for the probe slug (`mbqa_phpunit_sidebar_probe`), which drops the probe table, registry rows, and recursively removes `modules/{slug}/`. Do **not** use blanket `git clean -fd modules/` — that can delete unrelated uncommitted module work.

**PHP extensions (mandatory — every run):** PHPUnit 9 exits immediately unless the **CLI** `php.exe` subprocess loads **all** of:

`dom`, `json`, `libxml`, `mbstring`, `tokenizer`, `xml`, `xmlwriter`

If any are missing, PHPUnit prints: *PHPUnit requires the "dom", "json", … extensions, but the "mbstring" extension is not available.* (first missing extension named). The runner resolves CLI PHP via `itm_resolve_phpunit_cli_binary()` — **not** Apache `php-cgi`. Canonical list: `itm_phpunit_required_extensions()` in `includes/itm_cli_binary.php`.

**HTML coverage (additional):** `--coverage-html` needs **Xdebug** or **PCOV** with coverage enabled (`xdebug.mode` must include `coverage` for Xdebug). Without a driver, `run_tests.php` runs **Standard** mode with `--no-coverage` and shows a note. **Xdebug** is the usual choice on Windows Dunebox after `setup_dunebox_php_from_laragon.ps1`.

**Memory:** PHPUnit HTML report generation over the wide `phpunit.xml` include tree needs more than the default **128M** `php.ini`. `run_tests.php` passes **`-d memory_limit=512M`** to the PHPUnit subprocess (override with env **`ITM_PHPUNIT_MEMORY_LIMIT`**, e.g. `1024M` if static analysis still exhausts memory).

Verify on the same binary as `.env` **`PHP_EXE`** (or the path shown on the `run_tests.php` menu):

```powershell
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" -m
```

Expect **mbstring** plus **xdebug** (or **pcov**) before running **HTML coverage**.

| Mode | Browser | CLI |
|------|---------|-----|
| **Standard** (verbose, no coverage) | Open `scripts/run_tests.php` → **Standard** | `php scripts/run_tests.php` |
| **HTML coverage** | **HTML coverage** (background CLI — `coverage_job` log page; avoids gateway timeout) | `php scripts/run_tests.php --coverage` or `ITM_COVERAGE=1` |

**Browser gateway timeout (504):** do **not** run the full Xdebug suite inside Apache. `run=1&mode=coverage` shows a **Start background coverage run** page that spawns `php scripts/run_tests.php --coverage` detached and tracks **`qa-reports/run_tests_browser_coverage.log`**. Monitor at `scripts/run_tests.php?coverage_job=1`. **Standard** browser mode still streams output inline (~25s); use CLI if that times out too.
| **Skip DB tests** | Checkbox **Skip database tests** | `ITM_SKIP_DB_TESTS=1 php scripts/run_tests.php` |

**Coverage report:** after a successful HTML coverage run, open **`phpunit/coverage/html/coverage.html`** (PHPUnit writes `index.html`; `run_tests.php` renames it to `coverage.html`). The browser menu and post-run output link to this path when the file exists.

**Browser URLs:**

| Action | URL |
|--------|-----|
| Choose run mode | `scripts/run_tests.php` |
| Standard verbose run | `scripts/run_tests.php?run=1&mode=standard` |
| HTML coverage | `scripts/run_tests.php?run=1&mode=coverage` (intro → background job) |
| Coverage job log | `scripts/run_tests.php?coverage_job=1` |
| Skip DB + coverage | `scripts/run_tests.php?run=1&mode=coverage&skip_db=1` |

**Coverage driver:** `run_tests.php` resolves a **CLI** `php.exe` via `itm_resolve_phpunit_cli_binary()` (`includes/itm_cli_binary.php` — honours `.env` **`PHP_EXE`**, Dunebox default under **`D:\dunebox-v1.0.6`**, then Laragon portable `bin/php/php-7.4.33-…/php.exe`). It checks that subprocess for Xdebug/PCOV before `--coverage-html`. Stock Dunebox PHP has no `php.ini` (no **mbstring** / Xdebug) until you run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1
```

That copies **`php_xdebug.dll`** (and optional ext DLLs) from **Laragon portable** into **`D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\ext`**, writes **`php.ini`** beside `php.exe`, and patches **`D:\dunebox-v1.0.6\config\php\php-7.4.ini`** (used when `.env` **`PHP_EXE`** points at **`php74.cmd`**). Override paths with **`ITM_LARAGON_PHP_ROOT`** / **`ITM_DUNEBOX_PHP_ROOT`** / **`ITM_DUNEBOX_PHP_INI`**. Templates: `scripts/data/php.ini.dunebox-7.4.template`, `scripts/data/php.ini.dunebox-xdebug-snippet.ini`.

**`PHP_EXE`:** prefer **`…\php.exe`** for subprocess tools; **`php74.cmd`** is fine after setup patches the central ini. `run_tests.php` resolves **`php.exe`** with Xdebug for HTML coverage even when `.env` lists a `.cmd` shim.

Without a driver, the runner uses `--no-coverage` and shows a note (avoids PHPUnit’s “No code coverage driver available” warning).

**Windows Laragon (PowerShell):**

```powershell
cd C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\run_tests.php
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\run_tests.php --coverage
```

Linux/macOS/CI: bare `php scripts/run_tests.php` when PHP 7.4 is on PATH.

##### Safe matrix runner (`scripts/matrix_safe_run_once.php`)

Runs safe test matrix (Tiers 1-3) and outputs results in a `<pre>` block or JSON. Fully Browser + CLI compliant.
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
| `scripts/module_browser_qa_runner.php` | **Browser + CLI:** HTTP session runner — login (`Admin`/`Admin`), company scope, per-module **`mysql`** preflight (`db/` INSERT count), **`error_log`** scope, FK-aware clear, sample data, **`add`** (random rows capped by unique scope), **`bulk_delete`** after `add` when rows ≥ `records_per_page`, then search/sort/CRUD/export/**`clear_table`** (before second **`clear`**)/import/`single_delete`/end sample restore + **`error_log`** check. Early module/company preflight; auto-detected Base URL on Laragon (HTTPS→HTTP on localhost); structured **`import_db`** JSON parsing; stale AJAX progress cleanup; optional browser-only **UI click smoke** (one module + one company) appending `bulk_cancel_click`, `pagination_click`, `export_xlsx_click`, and `import_excel_click`. Browser **Run QA** silently runs `module_clean_tests_qa_runner.php` at start and end. Writes timestamped `qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json` and matching **`.xlsx`** each run. Form + **Run QA** (AJAX poll + **Stop**); do not use bare `?run=1` without `ajax=1`. |
| `scripts/module_browser_qa_build_report.php` | **Browser + CLI:** Builds markdown from a runner JSON (pick by date): summary, tier reference, configured step exceptions, **Results by module**, failure categories, **Failures only** and **Skip** quick indexes, preview in browser. Re-Run links preserve **UI click smoke** when set. Writes `qa-reports/module-browser-qa.md` (overwritten each build). |

**Scripts that write sample/test data (DB mutation):**

* `scripts/module_browser_qa_runner.php` (sample seed, random add rows, import round-trip rows).
* `scripts/employees_delete_clear_table_test.php` (creates temporary tenant + employee/access rows).
* `scripts/equipment_delete_clear_table_test.php` (creates temporary tenant + equipment/switch rows).
* `scripts/floor_plans_folder_move_test.php` (creates temporary folder hierarchy rows).
* `scripts/idfs_sync_human_test.php` (creates temporary equipment/switch/idf rows for end-to-end sync checks).
* `scripts/auth_register_reset_human_test.php` (creates disposable invitations and script-test employees for auth flows).
* `scripts/tickets_related_equipment_delete_test.php` (seeds sample ticket rows from `db/01_schema.sql`).
* `scripts/verify_tickets_sample_data.php` (empty-tenant tickets **Add sample data** — `TCK-0001`, active list visibility, no local employees, stale session `@app_company_id`).
* `scripts/verify_registration_invitations_sample_cycle.php` (registration_invitations **Add sample data** → soft-delete → re-seed loop for **companies 1–5** by default, or `--company=N` / `?company=N`; duplicate `(company_id, email)` restores soft-deleted row by `email` business key).
* `scripts/verify_employees_sample_cycle.php` (employees **Add sample data** → soft-delete visible rows → re-seed loop for **companies 1–5** by default, or `--company=N` / `?company=N`; duplicate `(company_id, username)` / `(company_id, work_email)` restores soft-deleted row by `username` + `work_email` business keys; resolves scalar subqueries in the sample template on restore).
* `scripts/verify_access_levels_sample_cycle.php` (access_levels **Add sample data** → soft-delete → re-seed loop for **companies 1–5** by default; live-row gate matches list; restores **Full** / **Limited** / **Read Only** by `name` business key).

**Script that dumps seed SQL (no DB writes):**

* `scripts/export_floor_plan_folders_seed.php` prints `INSERT` statements to stdout for pasting into `db/`.

**Commands (repository root, Laragon — PowerShell):**

```powershell
cd C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_build_report.php
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --pilot-only
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=expenses --company=4
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=departments --company=1
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=license_management --company=1
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=cable_colors --company=1
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/module_browser_qa_runner.php --module=expenses --company=1 --ui-click-smoke
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
| `$skipClear` | `companies`, `employees` |

Tier D modules run index navigation smoke only (`list`, `search`, `sort`); other steps are Pass with notes `N/A smoke`, `Skip (bespoke smoke)`, or `N/A`. **`$skipClear`:** tenant FK-aware clear is never run on these tables (shared auth). Tier D also skips start-of-module clear with note `Skip (bespoke smoke)`.

**Tier A step exceptions:** edit `mbqa_runner_module_step_exceptions()` in **`scripts/module_browser_qa_runner.php`** (module slug → step → N/A note). Mapped steps are **not executed**; all other Tier A steps still run. Examples: `employee_companies` skips `create`, `add`, `import_db`; `idf_positions` skips both `sample_data` steps with note `N/A (HTTP sample seed failed or empty)`; `patches_updates` skips both `sample_data` steps with note `No sample rows found in db/02_data.sql for this module.`; `audit_logs` skips read-only / delete-disabled steps (see runner map).

**Checklist per standard module (Tier A, including bespoke folders)** — step order (runner slug = table name unless a step exception applies):

| # | Step | What it checks |
|---|------|----------------|
| 1 | **`mysql`** | Whether `db/` defines sample `INSERT` rows for the module table. Parsed from `db/01_schema.sql` via `itm_parse_database_sql_inserts()` (same tuples as UI sample seed). Manual equivalent: `SELECT * FROM \`{table}\`` in phpMyAdmin on a fresh import — **0 row(s) (empty)** e.g. `ip_addresses`, or **N row(s)** e.g. `departments`. Informational **Pass**; note records the count. Fails only if `db/` is missing/unreadable. Tier C/D report `N/A`. |
| 2 | **`error_log`** | Start scope: rename `error_log.txt` to next `error_log-N.txt` when present; else record byte offset (only *new* lines count for this module). |
| 3 | **`list`** | Index HTTP 200, no fatal; Tier A also verifies bulk/pagination gates vs row count. |
| 4 | **`ui_check`** | When an Actions column is present on the index HTML, verifies **`class="itm-actions-cell"`** + **`data-itm-actions-origin="1"`** on the Actions header and at least one body cell when real data rows render (`js/ui-layout.js` / `table_actions_position`). Single-cell colspan empty-state rows (`No records found`, etc.) are ignored. |
| 5 | **`clear`** | FK-aware start-of-module tenant wipe (`companies` / `employees` skipped). |
| 6 | **`sample_data`** | HTTP sample seed; FK parents first; DB fallback via `itm_seed_table_from_database_sql()` when anchor ids differ. |
| 7 | **`add`** | Insert ~30 random tenant rows when count &lt; `records_per_page` + 1; grow unique-scope parents first. |
| 8 | **`pagination`** | Page 1 **▶️** + **⏭️** (`title="Next page"` / `title="Last page"`); page 2 **⏮️** + **◀️** (`title="First page"` / `title="Previous page"`) when rows &gt; `records_per_page`. |
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

**`mysql` verification (file or SQL):** prefer reading `db/` (runner step 1). To spot-check in MySQL: `SELECT COUNT(*) FROM \`{table}\`` on a database loaded from `db/01_schema.sql` — expect the same N as the runner note (global insert count, not per-tenant). Empty modules (`patches_updates`, `ip_addresses`, …) drive **`sample_data`** N/A notes such as `No sample rows found in db/02_data.sql for this module.`

**Tier A `add` / bulk UI (runner vs browser):**

| Step | Runner (`module_browser_qa_runner.php`) | Manual UI (`js/bulk-delete-selection.js` in `includes/header.php`) |
|------|----------------------------------------|---------------------------------------------------------------------|
| **`add`** | `mbqa_ensure_bulk_sample_rows()` — random inserts until tenant **live** row count (excludes `deleted_at` when present) ≥ `records_per_page` when schema/unique keys allow; skips `deleted_at` / `deleted_by` on insert so rows are not auto-soft-deleted | N/A (DB-only in QA) |
| **`bulk_cancel`** | Verifies index HTML: `bulk-delete-form`, `bulk-delete-selection.js`, `bulk_action`, **Select to Delete**; static or JS-injected **`data-itm-bulk-cancel="1"`** `type="button"` | First **Select to Delete** → checkboxes + **Delete Selected** + visible **Cancel**; **Cancel** exits without POST |
| **`bulk_delete`** | POST `modules/<slug>/delete.php` with `bulk_action=bulk_delete` and up to 3 `ids[]` (skips two-step UI) | Second click **Delete Selected** submits selected `ids[]` |

**FK-aware clear / delete:**

* **Tenant clear** walks inbound FKs (`information_schema`), deletes child rows for the active `company_id`, then clears the module table. MySQL **1451** retries parse the **child table** from the error text (`` `schema`.`child_table` ``), not the schema name.
* **`single_delete`** POSTs `delete.php`; on “in use by: `employee_positions` (1)” it clears parsed blocker tables (or `itm_find_record_usage`) and retries — **including blocker tables on bespoke modules** when required to unblock the delete.
* **Never auto-clear** during FK prep or delete retry: **`companies`** and **`employees`** only (shared auth).
* **Skip destructive clear** on `companies` and `employees` at the start of each module (same as before).

**Sample / export / import:**

* Sample seed prerequisites are seeded first when configured (e.g. `expenses` → `departments`, `budget_categories`, `cost_centers`, `gl_accounts`; `employee_positions` → `departments`).
* **`error_log`:** If `error_log.txt` cannot be renamed (e.g. Windows file lock), the runner records the current file size and only attributes **new** lines to the active module — avoids false failures from earlier modules. When rotation succeeds, archives are `error_log-1.txt`, `error_log-2.txt`, … under `ROOT_PATH`.
* **Export Excel** is simulated by parsing the list `<table>` HTML (same columns as `table-tools.js`).
* **Import Excel** POSTs **one** derived row to `data-itm-db-import-endpoint` (round-trip smoke, not re-import of every exported line). Uses export headers with insertable values from `db/01_schema.sql` when UI labels are not IDs. Export row payloads are captured from HTML **before** **`clear_table`** / the second **`clear`**. The runner runs **`clear_table`** (when the bulk gate passes) then **second `clear`** after **`export_xlsx`** so import runs on an empty table. **`expenses`:** import picks a **free** `cost_center_id` for the tenant (`uq_expenses_company_scope`); do not expect `inserted` to match export row count.

**Tiers (do not treat all failures alike):**

* **Tier A** — standard flattened CRUD (`modules/<slug>/index.php`), including modules with bespoke UI that still follow the Tier A checklist.
* **Tier C** — `is_*` façades (including `is_switch`): routing smoke on `list` / `search` / `sort`; other steps **N/A routing** in `mbqa_runner_module_step_exceptions()`.
* **Tier D** — `$bespokeSmoke` modules (`budget_report`, `expiring`, `rack_planner`, `floor_plans`, `companies`): navigation smoke only.

**UI click smoke:** browser form only — enable **UI click smoke**, select **one module** and **one company**, then **Run QA**. After the HTTP run finishes, JavaScript loads the module index in a hidden iframe and appends click-evidence steps via `ajax=ui_click_evidence`. CLI `--ui-click-smoke` is a guard that exits with instructions to use the form.

**Cursor browser:** Use IDE browser for the **Expenses pilot** (all five companies) and spot-checks; use the CLI runner for full ~101×5 coverage. Latest results: **`qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json`**, matching **`.xlsx`**, and **`module-browser-qa.md`** (commit when publishing QA results).

**Caveats:** Run lookup parents before children (see `$lookupWave` in the runner). Sort-step failures often mean the visible default column is not `id`; confirm via column header links. Modules without `data-itm-db-import-endpoint` report `import_db` as N/A.

### Company module access scripts

| Script | Purpose |
|--------|---------|
| `php scripts/sync_modules_registry.php` | Upsert `modules_registry` from filesystem + sidebar-excluded slugs; bulk backfill when sidebar auto-register is not enough |
| `php scripts/verify_company_module_access.php` | Regression: registry coverage, opt-out deny, excluded slugs in admin matrix, sidebar discovery probes (registry-only / new MySQL table / folder-only / both / neither); PHPUnit: `CompanyModuleAccessVerifyTest` |
| `php scripts/benchmark_sidebar_module_access.php` | Read-only benchmark: MySQL `Questions` delta for live sidebar path (`itm_sidebar_structure()` + `has_module_access()` filter) vs uncached legacy N+1 simulation; median query count, timing, and reduction %; env thresholds `ITM_BSMA_MAX_FULL_QUERIES` (default 45), `ITM_BSMA_MIN_REDUCTION_PCT` (default 50) |
| `php scripts/seed_company_module_access.php` | Optional backfill of explicit `company_module_access` rows (`enabled=1`) |
| `php scripts/apply_new_company_module_share_capable_seed.php` | Backfill `company_module_share` for share-capable slugs only (equivalent to `db/migrations/company_module_share_capable_seed.sql`). Dry-run default; `--apply` or `?apply=1` (Admin). Optional `--company=N` / `?company=N` for one tenant. |
| `php scripts/verify_auto_scaffolding.php` | Verification: dynamic auto-scaffolding toggle. Checks scaffolding behaviors when auto-scaffolding is enabled or disabled. |

Run `sync_modules_registry.php` after adding module folders; run `verify_company_module_access.php` when changing `includes/itm_company_module_access.php` or enforcement hooks. Run `benchmark_sidebar_module_access.php` after sidebar discovery or module-access caching changes to confirm query reduction (expect large drop vs legacy simulation when prefetch cache is enabled; marketing figures ~417→~7 depend on module count and environment — treat this script as the authoritative local measurement).

### Administrative Tools / Developer Bypass

| Script | Purpose |
|--------|---------|
| `php scripts/bypass_login.php` | CLI-only Admin session hijack for dev/Playwright (non-admin users rejected via `itm_is_admin()`). Sets up Admin user, TechCorp Global company, and Vault master key. |
| `php scripts/bypass_v2.php` | CLI-only Admin session hijack for dev/Playwright (non-admin users rejected via `itm_is_admin()`). Sets up Admin user, TechCorp Global company, and Vault master key. |

### Roles & Permissions scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_roles_permissions.php` | Regression: `modules_registry` row, module folder + JS, RBAC exempt slug, Admin `ALL` wildcard with six flags, seeded roles/hierarchy for company 1, `can_import`/`can_export` columns, role sidebar `active_count` (role_id + HR Active) |
| `php scripts/verify_demo_module_restrictions.php` | Regression: seed admins (`Admin`, `Admin2`–`Admin5`) password + `itm_is_admin()`; demo users `demo1`–`demo5` single-module `has_module_access` / RBAC `can_view` + subprocess index probes (contract: `lib/itm_demo_module_restrictions_contract.php`) |
| `php scripts/verify_employee_contact_email.php` | Regression: at least one of `work_email` / `personal_email` required (`includes/itm_employee_contact_email.php`); helper unit checks, create/edit static wiring, `fast_create_acc` both fields, disposable employee create |
| `php scripts/verify_sso_ldap.php` | Regression: LDAP SSO schema columns (`companies.sso_*`, `employees.sso_subject`), `itm_ldap_encrypt_config` / `decrypt_config` round-trip, PHP `ldap` extension probe (N/A when missing), `sso-ldap.php` + `includes/itm_ldap_auth.php` helpers |
| `php scripts/verify_vault_org_recovery.php` | Regression: vault org recovery schema, escrow crypto, consent + request + complete workflow (`includes/itm_vault_org_recovery.php`, `modules/vault_org_recovery/`, `user-config.php` consent hooks) |
| `php scripts/fast_create_acc.php` | CLI `--seed-demo-bundle`; browser UI at `scripts/fast_create_acc.php` (catalog) |
| `scripts/fast_create_acc_browser.php` | Browser alias — same UI as `scripts/fast_create_acc.php` |
| `modules/employees/fast_create_acc.php` | Same UI via module toolbar 🚀; shared form in `fast_create_acc_browser.php` |
| `php scripts/check_fast_create_acc_select_quick_add.php` | Static audit: FK `<select>` in `modules/employees/fast_create_acc_browser.php` includes `__add_new__` ➕ (exempt `module_slugs[]`, `bundle_company_id`, `company_ids[]`) |
| `php scripts/check_department_select_quick_add.php` | Static audit: every department FK `<select>` in `modules/` and `scripts/` includes `__add_new__` ➕ quick-add (per-select block) |
| `php scripts/verify_dashboard_active_employees.php` | Regression: **admin.php** row 2 **Active** / **On Leave** call `itm_employee_count_by_employment_status_name()` (no inline `LOWER(es.name)`); helper matches live `deleted_at IS NULL` counts; employee `dashboard.php` must not duplicate company counts; optional `ITM_TEST_COMPANY_ID` |
| `php scripts/verify_dashboard_online_employees.php` | Regression: **admin.php** **Online now** stat, session presence touch hook, count after touch |
| `php scripts/verify_employee_dashboard.php` | Regression: employee **dashboard.php** hero + grouped stat cards, `includes/itm_employee_dashboard.php` loader, no company switcher |
| `php scripts/verify_admin_page_gate.php` | Regression: **admin.php** `itm_is_admin()` gate and redirect to `dashboard.php` |
| `php scripts/verify_settings_admin_buttons.php` | Regression: Settings **ADMIN** / **SCRIPTS** toolbar (admin-only), **All roles** chatbot block, **System (Admin Role only)** flags, and non-admin save preservation |

Run `verify_roles_permissions.php` when changing `modules/roles_permissions/`, `js/roles-permissions-matrix.js`, `includes/itm_role_module_permissions.php`, or `employee_roles` / `role_module_permissions` / `role_hierarchy` schema in `db/03_triggers.sql`.

Run `verify_demo_module_restrictions.php` when changing demo user seeds (`demo1`–`demo5`), their dedicated `employee_roles` / `role_module_permissions` rows, or company-module / RBAC enforcement for single-module demo accounts.

Run `verify_employee_contact_email.php` when changing employee email validation, `includes/itm_employee_contact_email.php`, employees create/edit/import paths, or `fast_create_acc.php` email fields.

Run `verify_sso_ldap.php` when changing LDAP SSO login (`includes/itm_ldap_auth.php`, `sso-ldap.php`, `login.php` SSO link, or Companies edit SSO fields).

#### PHP `ldap` extension (LDAP SSO login)

**Live LDAP auth:** [sso-ldap.php](http://localhost/it-management/sso-ldap.php) requires PHP **`ldap`** on the **Apache** SAPI (`ldap_connect()` in `itm_ldap_auth_attempt()`). Missing extension → user-facing error *LDAP extension is not loaded on this server.*

**Not the same as IMAP:** inbound email (`run_inbound_email_tickets.php`) needs **`imap`** on the **CLI** cron binary; LDAP SSO needs **`ldap`** on the **web** server. Enable both in the same `php.ini` when Apache and CLI share one PHP 7.4.33 install (typical Laragon/Dunebox).

| Environment | Enable `ldap` |
|-------------|----------------|
| **Dunebox** | `extension=ldap` in `D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.ini` (template: `scripts/data/php.ini.dunebox-7.4.template`). Copy `php_ldap.dll` into `ext\` from Laragon portable if missing. Re-run `powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1` when the template changes; **restart Apache**. |
| **Laragon portable** | `extension=ldap` in `bin\php\php-7.4.33-nts-Win32-vc15-x64\php.ini`; confirm `ext\php_ldap.dll`. Restart Apache. |
| **Linux** | `php-ldap` package (or `extension=ldap` in Apache `php.ini`). |

**Verify:**

```powershell
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" -r "echo function_exists('ldap_connect') ? 'ldap ok' : 'ldap missing';"
```

Browser regression: [verify_sso_ldap.php?run=1](http://localhost/it-management/scripts/verify_sso_ldap.php?run=1) (extension probe is **N/A** when missing — see `docs/SSO_LDAP.md`).

Run `verify_dashboard_active_employees.php` when changing `admin.php` or `includes/itm_employee_employment_status.php` Active/On Leave count logic.

Run `verify_dashboard_online_employees.php` when changing `admin.php`, `includes/itm_active_sessions.php`, or the session presence hook in `config/config.php`.

Run `verify_employee_dashboard.php` when changing employee `dashboard.php`, `includes/itm_employee_dashboard.php`, or `includes/itm_employee_dashboard_cards.php`.

Run `verify_admin_page_gate.php` when changing `admin.php` access control.

Run `verify_settings_admin_buttons.php` when changing Settings admin shortcuts in `modules/settings/index.php`.

Screenshots for README: `python3 scripts/take_screenshots_modules.py` (default modules: `todo`, `notes`, `roles_permissions`, `system_status`; output under `docs/readme/`). Requires Playwright + local Apache at `http://localhost/it-management/`. Uses `scripts/bypass_login.php` plus `sudo chown www-data:www-data` on the sess file so Apache accepts the cookie; derives `PHPSESSID` cookie domain from the screenshot base URL hostname (`urlparse`). Env vars:

| Variable | Purpose |
|----------|---------|
| `ITM_SCREENSHOT_BASE_URL` | Base app URL (default `http://localhost/it-management`) |
| `ITM_SCREENSHOT_ONLY` | Comma-separated module slug(s) to capture; legacy `1`/`true`/`yes` → `system_status` only |
| `ITM_SCREENSHOT_MODULES` | Override default module list when `ITM_SCREENSHOT_ONLY` is unset |

Examples:

```bash
ITM_SCREENSHOT_ONLY=roles_permissions python3 scripts/take_screenshots_modules.py
ITM_SCREENSHOT_ONLY=system_status python3 scripts/take_screenshots_modules.py
```

`roles_permissions` waits for `#rp-permission-matrix` before saving `docs/readme/roles_permissions.png`. `system_status` waits for `#system-info-content` before saving so README does not show the login page or an empty cache warning.

### Sidebar module-access benchmark (`benchmark_sidebar_module_access.php`)

| Item | Detail |
|------|--------|
| **Purpose** | Verify the per-request prefetch cache in `has_module_access()` and batch registry ensure reduce sidebar-related query volume. |
| **Method** | Uses `SHOW SESSION STATUS LIKE 'Questions'` (same pattern as `verify_metadata_column_cache.php`). Optimized path mirrors `includes/sidebar.php`: fresh `itm_sidebar_structure($conn, true)`, `itm_sidebar_item_catalog()`, then one `has_module_access()` per sidebar module slug. Legacy path re-runs uncached per-slug registry, admin, and CMA queries plus a separate per-slug registry ensure simulation. |
| **CLI** | `php scripts/benchmark_sidebar_module_access.php` · `--company=1 --employee=1 --iterations=3 --checks=100` |
| **Browser** | `scripts/benchmark_sidebar_module_access.php` (optional query params `company`, `employee`, `iterations`, `checks`) |
| **Shared lib** | `scripts/lib/itm_benchmark_sidebar_access.php` |
| **PASS** | Median optimized full-path queries ≤ `ITM_BSMA_MAX_FULL_QUERIES` (default `0` = auto: `max(50, ceil(slugs × 0.85) + 15)`) **and** reduction vs legacy combined estimate ≥ `ITM_BSMA_MIN_REDUCTION_PCT` (default 50). Component checks (BOLT journal): optimized `has_module_access` ×100 ≤ `ITM_BSMA_JOURNAL_ACCESS_OPTIMIZED_MAX` (default 5), legacy ×100 ≥ `ITM_BSMA_JOURNAL_ACCESS_LEGACY_MIN` (default 150), optimized `itm_sidebar_structure` ≤ `ITM_BSMA_JOURNAL_STRUCTURE_OPTIMIZED_MAX` (default `0` = auto: `max(25, ceil(slugs × 0.85))`), access-only timing reduction ≥ `ITM_BSMA_JOURNAL_TIMING_MIN_PCT` (default 50). Prints second `itm_sidebar_structure()` cache reuse (expect 0 queries per request). |
| **BOLT journal** | Reference claims (19-06-2026): full sidebar ~417→~7; `has_module_access` ×100 ~200→~2; `itm_sidebar_structure` ~171→~6; ~75% faster on mocked 100 checks. The script prints `[MATCH]` / `[DIFFERS]` vs measured values with tolerance; see `docs/bolt.md`. |
| **Notes** | Read-only; no CMA mutations. Without prefetch cache helpers the optimized path stays high and the script warns. Absolute query totals vary by registry row count and discovery — compare optimized vs legacy on the same database rather than hard-coding ~417 or ~7. |

Catalog: `scripts/scripts.php`.

### Ops Report scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_ops_report.php` | Regression: D-2 edit lock, `ops_report` CRUD, child cascade delete, cross-date hit line format (`keyword \| day — sections`), audit triggers on all `ops_report*` tables, `modules_registry` row; PHPUnit: `OpsReportTest`, `OpsReportPermissionsTest` |
| `php scripts/verify_expenses_ap.php` | Regression: AP lookups seeds, tax snapshot, Draft vs Posted budget-actual filter, EUR expense insert probe |
| `php scripts/verify_bills.php` | Seed bill + line rollups, supplier FK, `itm_expenses_post_from_bill()` (bill_id + invoice_number) and duplicate-post guard |
| `php scripts/verify_invoices.php` | Seed invoice + line rollups, `itm_expenses_post_from_invoice()` (invoice_id + invoice_number) and duplicate-post guard |
| `php scripts/verify_finance_payment_allocations.php` | Payment allocations and `amount_due` on bills |
| `php scripts/verify_finance_attachments.php` | Finance attachments table, path helpers, allowed types |
| `php scripts/verify_expense_recurrence.php` | `expense_recurrence` seeds and advance-date helper |
| `php scripts/run_expense_recurrence.php` | CLI runner for due recurring expense templates (`--company=1`) |
| `php scripts/verify_customers.php` | Seed customer and invoice `customer_id` |
| `php scripts/verify_integration_accounts.php` | integration_accounts seed + insert probe |
| `php scripts/verify_bank_accounts.php` | bank_accounts seed + insert probe |
| `php scripts/verify_ops_report_sample_data.php` | Empty-tenant **Add sample data** for all seven `ops_report_*` child modules (`ops_report_id` parent seed). Mutates company `4` test rows. |
| `php scripts/seed_ops_report_search_demo.php` | Seeds company 1 demo rows (`DemoManager` on two past dates) for manual QA and screenshot script; prints expected hit lines |
| `python scripts/verify_ops_report_search_screenshot.py` | Seeds demo data, bypass login, captures five human-flow PNGs under `qa-reports/ops_report_search/` (all-dates hits, section filter, sort, this-day navigation, search bar). Playwright + local Apache. Env: `ITM_SCREENSHOT_BASE_URL`, `ITM_PHP_BIN`, `ITM_OPS_SEARCH_DEMO_KEYWORD` |

Run `verify_ops_report.php` when changing `modules/ops_report/` or `ops_report*` tables in `db/03_triggers.sql`.

### Reports Hub scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_reports_hub.php` | Regression for `modules/reports/`: all `api/helpers.php` chart payloads, Hotel Operations MTD metrics (`ops_report` / `ops_report_fb_outlet`), budget vs actual / YoY totals, `modules_registry` slug `reports`, and core Chart.js canvas ids in `index.php`. Optional `ITM_TEST_COMPANY_ID` (default 1). Requires `db/` Reports Hub sample seeds (ops_report daily trend, F&B covers, expanded budgets/expenses). Browser + CLI. |

Run `verify_reports_hub.php` when changing `modules/reports/`, `modules/reports/api/helpers.php`, or Reports Hub-related seeds in `db/03_triggers.sql`.

### Scheduled reports, saved views, webhooks, and asset depreciation

| Script | Purpose |
|--------|---------|
| `php scripts/run_scheduled_reports.php` | Cron: email due `scheduled_reports` rows (`includes/itm_scheduled_reports.php`). Executive catalog slugs + `saved_view:{id}`. Optional `--company=ID`. See `docs/SCHEDULED_REPORTS.md` and `docs/SAVED_REPORT_VIEWS.md`. |
| `php scripts/verify_scheduled_reports.php` | Regression: `scheduled_reports` schema, cron matcher, dataset loader |
| `php scripts/verify_saved_report_views.php` | Regression: `saved_report_views` schema, filter whitelist, export/share helpers, owner schedule permission, dashboard snapshot, save/run, `saved_view:{id}` slug. See `docs/SAVED_REPORT_VIEWS.md`. |
| `php scripts/verify_ticket_surveys.php` | Regression: ticket questionnaires/surveys — five-company seeds, issue/submit, activity, webhook `ticket.survey_completed`, automation `send_ticket_survey`, merge `{{survey_url}}`, merge cancel pending, stats. See `docs/TICKET_SURVEYS.md`. |
| `php scripts/run_webhook_queue.php` | Cron: deliver `integration_webhook_deliveries` with retry/backoff. Optional `--limit=N`. |
| `php scripts/verify_integration_webhooks.php` | Regression: webhook tables, secret round-trip, URL SSRF guard, enqueue |
| `php scripts/run_asset_depreciation.php` | Monthly cron: depreciation snapshots on `equipment_lifecycle_events`. Optional `--company=ID`. |
| `php scripts/verify_asset_depreciation.php` | Regression: equipment lifecycle columns, months-elapsed math, book value sample |

Run the matching `verify_*.php` after changing `includes/itm_scheduled_reports.php`, `includes/itm_saved_reports.php`, `includes/itm_webhook_queue.php`, `includes/itm_asset_depreciation.php`, or related `db/` DDL.

### Appointment scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_appointment.php` | Regression: appointment tables/triggers, `booking_lock` index, seeds, slot builder, inactive-settings gate, past-slot availability, ICS builder smoke, company 1 modality sample, `modules_registry` slugs |
| `php scripts/verify_appointment_settings.php` | Regression: `modules/appointment_settings/` admin UX — bulk hours grid, visit-reason reorder, session flash, settings delete guard, visit-reason unique index, `appt_user_can_access_settings()` |
| `php scripts/apply_appointment_visit_reasons_replicate.php` | Copy `appointment_visit_reasons` from company 1 to all other companies (`INSERT IGNORE` on `company_id` + `name`). Dry-run default; `--apply` or browser `?run=1&apply=1` (Admin). Live DBs that imported before visit-reason seeds moved ahead of `@replicate_source_company_id`. |

Run `verify_appointment.php` when changing `modules/appointments/`, `includes/itm_appointment.php`, or appointment DDL/seeds/triggers in `db/`. Run `verify_appointment_settings.php` when changing `modules/appointment_settings/` or `includes/itm_appointment_settings_admin.php`.

### Hotel booking scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_hotel_booking.php` | Regression: core `hotel_booking_*` / `booking_rooms_types` tables, **`hotel_booking_last_rooms`**, planning Arrivals/Departures/In-house/Future + hide filters, segment helper (`future`), company 1 `PENDING` future status seed, portal rate plan form contract, confirmation PDF Manage my booking `/URI` link helper + markup, **`hotel_bookings.auth2`** strong guest code helpers (`varchar(12)`), **manage session + IP rate-limit**, **email OTP verify**, **Step 4 DB charge resolve** (`itm_hotel_booking_portal_resolve_step4_charge`) + **locked INSERT (`FOR UPDATE`)**, **Step 4 draft occupancy lock** + **`public_portal_enabled` browse/book gate** wiring, sample hotel photo seeds (`hb_seed_*.jpg`), portal fallback JPGs (`image_2.jpg`, `room-3.jpg`, `room-5.jpg`, `room-6.jpg`), stay-bar occupancy interactive only on `rooms.php`, cancellation-policy URL extension allowlist (`.html`/`.htm`/`.txt` only) + `booking/cancellation_policy/.htaccess`, calendar/From/Step 1 show **cheapest** tax-inclusive rate (STD BAR **75.00** × NR **10%** + tourist tax ≈ **69.50**; `prices_include_tax`; money format keeps cents; Select Dates auto-advance from `hotel_booking_settings.calendar_month_advance_days_left`, seed **3**; `show_discount_strikethrough` admin toggle + portal Step 1/2 wiring, seed **1**), seed room `id=1` STD, portal rate-plan offer labels (Flexible vs Non-refundable + NR 10% discount + `plan_surcharge_percent`), rooms list JS `updatePrices()` includes tourist tax + cheapest plan discount, **free cancellation days** settings + rate-plan merchandising seeded for companies **1–5**, hotel list **From** uses `itm_hotel_booking_portal_cheapest_rate_offer_for_hotel()`, **room-type portal rules** (`itm_hotel_booking_portal_room_type_*` helpers: occupancy, stay validation, complimentary credit, draft pets/approval; static guards on `booking/rooms.php` `cardQuoteOccupancy` + `customize.php` pets gate; live `SHOW COLUMNS` for `booking_rooms_types.portal_bookable` + `hotel_booking_settings.portal_complimentary_*`), PHPUnit mirror: `HotelBookingRoomTypePortalRulesTest`, and subprocess render probe for all 13 Hospitality sidebar `index.php` modules |
| `php scripts/verify_hotel_booking_distribution.php` | Regression: `hotel_booking_distribution_*` tables (including rate-plan mappings, ARI restrictions, webhook queue), API key helpers, inbound signature, delta checksum, ACK/NACK, OpenTravel XML parse/encode, modify + webhook helpers, availability builder |
| `php scripts/verify_stripe_checkout.php` | Regression: Stripe Checkout columns on `hotel_bookings` / `hotel_booking_settings`, `hotel_booking_payment_events` table, encrypt round-trip, webhook HMAC probe, mock `checkout.session.completed` payload (no live Stripe API) |
| `php scripts/verify_hotel_booking_distribution_http.php` | HTTP regression: disposable channel, `probe` auth (401 without key, 200 with key), availability GET over curl. Default target is a built-in PHP server (not Apache); browser `?run=1` uses the same path to avoid gateway timeout |
| `php scripts/verify_hotel_booking_distribution_opentravel_coverage.php` | OpenTravel OTA parse/encode: AvailRQ, ResNotifRQ, AvailNotifRQ, PingRQ |
| `php scripts/verify_hotel_booking_distribution_booking_com_cert.php` | Booking.com Connectivity offline cert checklist (ACK/NACK, notify, rates payload) |
| `php scripts/report_hotel_booking_distribution_webhook_ops.php` | Ops report: dead-letter + failed webhook queue rows; exit `1` unless `--allow-dead` |
| `php scripts/run_hotel_booking_distribution_ari_sync.php` | Push ARI snapshots to all active channels with `webhook_url` (optional `--company=`, `--days=`) |
| `php scripts/run_hotel_booking_distribution_webhook_queue.php` | Process pending/failed outbound webhook queue rows with exponential backoff; dead-letter at `max_attempts` |
| `php scripts/verify_hotel_booking_distribution_webhook_ssrf.php` | Regression: outbound `webhook_url` SSRF guard (`itm_hotel_booking_distribution_validate_webhook_url`, channel edit save, deliver without HTTP to blocked hosts) |
| `php scripts/seed_hotel_booking_sample_photos.php` | Copy sample hotel + room-type images to `booking/images/{hotel_id}/hotel_photos/` and `room_types_photos/` for **companies 1–5** from `booking/images/sample-room.jpg` (and legacy portal assets when present); upsert photo rows — **Browser + CLI** via `itm_apply_script_bootstrap.php` (`--apply` / `?run=1&apply=1` writes; default dry-run) |
| `php scripts/sample_bookings.php` | Seed **10** `hotel_bookings` rows per company **1–5** with realistic guest names and lifecycle statuses (PENDING, CONFIRMED, CANCELLED, IN-HOUSE, DUE-IN, DUE-OUT, NO-SHOW, CHECKED-OUT) anchored on today — **Browser + CLI** via `itm_apply_script_bootstrap.php` (`--apply` / `?run=1&apply=1` writes; replaces prior `[sample_bookings]` notes) |
| `php scripts/check_hotel_bookings_rate_plan_form.php` | Static gate: `__add_new__` portal rate plan select, `hb_booking_end_form_page()` body-level modal, rate-plan select JS quick-add handler |
| `php scripts/check_hospitality_date_format.php` | Hospitality-only subset of `check_date_format.php` (stay-date static + helper contracts) |
| `php scripts/check_date_format.php` | Project-wide date format gate: UK `dd/mmm/yyyy`, hospitality `d/M/Y`, audit stamps, hospitality static scan, scaffold cell-hook info |

Run `verify_hotel_booking.php` when changing `modules/hotel_bookings/`, `booking/`, `includes/itm_hotel_booking.php`, or hotel booking DDL/seeds/triggers in `db/`. Run `verify_stripe_checkout.php` when changing `includes/itm_stripe_checkout.php`, `booking/payment-stripe.php`, `booking/stripe-webhook.php`, portal Step 4 Stripe flow, or Stripe columns in `hotel_booking_settings` / `hotel_bookings`. Run `verify_hotel_booking_distribution.php` when changing `modules/hotel_booking_api/`, `modules/hotel_booking_distribution_channels/`, distribution adapter includes, or distribution DDL in `db/`. Run `verify_hotel_booking_distribution_http.php` after HTTP/auth/router changes to `modules/hotel_booking_api/api.php`. Run `verify_hotel_booking_distribution_opentravel_coverage.php` after OpenTravel parse/encode changes. Run `verify_hotel_booking_distribution_booking_com_cert.php` after Booking.com adapter or credential changes. Run `report_hotel_booking_distribution_webhook_ops.php` for dead-letter monitoring (cron/ops). Run `run_hotel_booking_distribution_ari_sync.php` after webhook or outbound ARI changes. Run `run_hotel_booking_distribution_webhook_queue.php` after webhook queue or retry logic changes. Run `check_hotel_bookings_rate_plan_form.php` when changing hotel bookings create/edit form or `js/hotel-bookings-rate-plan-select.js`. Run `check_hospitality_date_format.php` or `check_date_format.php` when changing date display, parse, or form widgets (`includes/itm_date_format.php`, `includes/itm_hotel_date_input.php`).

### Email Management scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_emails_module.php` | Regression: `emails`, `email_smtp_configurations`, `email_alert_rules` tables, `modules_registry` row, default SMTP seed, alert rule seeds, `itm_send_email()` helper; static vault-key notification contract in `user-config.php` (subjects, transactional template, no master-key variables in `itm_send_email()` args); company 1 warranty/license **30-day alert window is a hard fail** (script inserts disposable license sample when empty, then deletes it). `db/` uses relative `DATE_ADD(CURDATE(), …)` expiry seeds so fresh imports stay in-window |
| `php scripts/verify_webmail_module.php` | Regression: `modules/webmail/` folders (inbox, starred, sent, archived, trash), star/archive toggles, soft/hard delete, `modules_registry` row `webmail` on shared `emails` table |
| `php scripts/verify_user_config_profile.php` | Regression for `user-config.php` profile fields: home-company UPDATE vs tenant switcher, birthday/theme/emergency round-trip, profile photo URL must be app-absolute `modules/explorer/file.php` (not `../../modules/…`); cross-tenant `file.php` profile read denied; static `$ui_config` reload after sidebar save |
| `php scripts/verify_sidebar_preferences.php` | Regression: canonical section normalize/reconcile (`explorer` → `employee`), section visibility sync, `employee_sidebar_preferences` DB round-trip, Settings SideMenu access-gate static wiring, `user-config.php` fresh `$ui_config` contract |
| `php scripts/run_email_alert_rules.php` | Dispatches enabled alert rules per company (warranty, license, certificate, alerts, notes, to-do, events); warranty matches also enqueue in-app notifications for equipment assignees; optional `--company=1` and `--verbose` (per-rule match/sent notes when count is 0) |
| `php scripts/run_inbound_email_tickets.php` | Polls per-company inbound mail (IMAP or local **Mailpit** API when `imap_host` = `mailpit`) and creates or updates `tickets` from mail routed to `companies.email`; optional `--company=1`, `--verbose`, `--dry-run` |
| `php scripts/apply_mailpit_inbound_email_config.php` | Sets `imap_host=mailpit` and enables inbound tickets on each default SMTP profile (dry-run default; `--apply` or browser `apply=1`) for Laragon/Dunebox + [Mailpit](http://localhost/mailpit/) |
| `php scripts/send_mailpit_inbound_test_email.php` | Sends a test message **To** a tenant `companies.email` via Mailpit SMTP (`127.0.0.1:1025`); optional `--process` runs inbound ticket creation for that company |
| `php scripts/verify_inbound_email_tickets.php` | Regression for inbound parsers, dedupe, threading, keyword routing, event logging, requester resolution, schema columns; live Mailpit E2E when API at `http://localhost/mailpit/api/v1` responds |
| `php scripts/run_notification_digest.php` | Sends digest emails for employees with unread `employee_notifications` rows; optional `--company=1` |
| `php scripts/run_ticket_sla_monitor.php` | Stamps `sla_*_breached_at`, logs `ticket_activity`, notifies assignees; optional `--company=1`; schedule every 15 min |
| `php scripts/verify_ticket_sla_dashboard.php` | Regression for SLA Command Center (`includes/itm_ticket_sla.php`, `modules/ticket_sla_dashboard/`, breach columns) |
| `php scripts/verify_employee_notifications.php` | Regression for `itm_notify_employee()`, unread count, mark read, header API/JS assets |
| `php scripts/verify_approval_inbox.php` | Regression for `includes/itm_approval_inbox.php`, `approval_inbox_items` upsert/fetch, adapter registry |
| `php scripts/verify_problem_management.php` | Regression for Problem Management + Known Error DB (`includes/itm_problem_management.php`, ticket linking, KB publish, suggest). See `docs/PROBLEM_MANAGEMENT.md`. |
| `php scripts/verify_cmdb.php` | Regression for CMDB Lite (`includes/itm_cmdb.php`, `configuration_items*`, `change_requests`, rack/subnet/system_access sync, cycle detection, impact BFS, equipment/IDF/rack sync hooks). |
| `php scripts/test_email_forgot.php` | Manual forgot-password email test via `itm_send_email()` / tenant SMTP; creates a real 24-hour reset token for the matching employee before sending; CLI supports `--company=1` (defaults to session company or `1`) |
| `php scripts/test_register_mail.php` | Manual registration welcome email test via `itm_send_email()`; CLI supports `--company=1` |

Run `verify_emails_module.php` when changing `modules/emails/`, `includes/itm_email.php`, `user-config.php` vault-key notification mail, or `email*` tables in `db/03_triggers.sql`.

Run `verify_inbound_email_tickets.php` when changing `includes/itm_inbound_email_tickets.php`, `scripts/run_inbound_email_tickets.php`, inbound SMTP columns, `ticket_inbound_email_messages`, or `ticket_inbound_email_routing_rules` in `db/01_schema.sql`.

#### PHP `imap` extension and local Mailpit (inbound email → tickets)

**Local dev (Laragon/Dunebox):** set `imap_host` to **`mailpit`** on the default SMTP profile (seed data and `apply_mailpit_inbound_email_config.php`). Outbound mail uses SMTP **`127.0.0.1:1025`**; inbound polling uses the Mailpit HTTP API at **`http://localhost/mailpit/api/v1`** (web UI: [http://localhost/mailpit/](http://localhost/mailpit/)). No PHP `imap` extension required when all enabled profiles use Mailpit. Mailpit is a **shared** local inbox — the runner skips messages whose To/Cc does not include that tenant `companies.email` (opening mail in the Mailpit UI does not block processing; dedupe is `ticket_inbound_email_messages`).

**Production IMAP:** `run_inbound_email_tickets.php` calls `imap_open()` when `imap_host` is a real mailbox host — the extension must be loaded on the **CLI** `php.exe` used for cron, not only Apache.

| Environment | Enable |
|-------------|--------|
| **Dunebox** | Add `extension=imap` to `D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.ini` (template: `scripts/data/php.ini.dunebox-7.4.template`). Copy `php_imap.dll` into `ext\` from Laragon portable if missing. Re-run `powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1` when the template changes. |
| **Laragon portable** | Uncomment `extension=imap` in the `php.ini` beside the PHP 7.4.33 binary under `bin\php\php-7.4.33-…\`. |
| **Linux / CI** | Install `php-imap` (or enable `extension=imap` in distro `php.ini`) for the CLI SAPI. |

**Verify (CLI):**

```bash
php scripts/apply_mailpit_inbound_email_config.php --apply
php scripts/verify_inbound_email_tickets.php
php scripts/run_inbound_email_tickets.php --dry-run
php -r "echo function_exists('imap_open') ? 'imap ok' : 'imap missing';"
```

`verify_inbound_email_tickets.php` runs a live Mailpit end-to-end when the API responds; parser/dedupe/routing/logging tests pass without Mailpit or `imap`.

**Example tests covered** (see script landing `?run=1` for the full list):

| Area | Examples |
|------|----------|
| Duplicate prevention | Message-ID dedupe row + skip on reprocess |
| Threading | `TCK-####`, `[#id]`, `Re:`/`Fwd:` subject, `In-Reply-To` / `References` |
| Keyword routing | `urgent`, `critical`, `billing`, `support` → priority / category / assignee |
| Event logging | `emails.details` JSON (`inbound_event`, `raw_payload` on `failed`) |
| Mailpit E2E | New ticket, `URGENT` priority, threaded reply comment |

Run `verify_webmail_module.php` when changing `modules/webmail/` or webmail-related `itm_send_email()` log options.

Run `verify_user_config_profile.php` when changing `user-config.php`, `includes/employee_profile_photo.php`, or Explorer `file.php` profile-photo serving (URL contract, home-company UPDATE scoping, cross-tenant deny).

Run `verify_sidebar_preferences.php` when changing `user-config.php` Personalized Sidebar, `modules/settings/` SideMenu, or `includes/ui_config.php` sidebar save/load helpers.

### System Status scripts

| Script | Purpose |
|--------|---------|
| `php scripts/verify_system_status.php` | Regression: module files, `modules_registry` row, native API payloads, `system_status` cache table refresh/read, storage tree + active DB table reports, `information_schema` size query; on Windows also checks `shell_exec`, `is_readable()` on each `includes/*.ps1`, and runs each `test_*.php` PowerShell wrapper |
| `php scripts/system_status_api.php` | Admin JSON dispatcher (`?action=…`). PHP/MySQL actions always native; Windows hardware uses `includes/*.ps1`. Action allowlist in dispatcher and `itm_system_status_run_powershell_action()`. Documented in `scripts/api.php`. |
| `php scripts/system_status_phpinfo.php` | Admin-only full `phpinfo()` for the active Apache SAPI (browser; requires Admin session) |
| `php scripts/test_system_info.php` | Validates `includes/system_info.ps1` JSON (Windows; skips with warning when PowerShell unavailable) |
| `php scripts/test_cpu_usage.php` | Validates `cpu_usage.ps1` |
| `php scripts/test_ram_usage.php` | Validates `ram_usage.ps1` |
| `php scripts/test_disk_usage.php` | Validates `disk_usage.ps1` |
| `php scripts/test_uptime.php` | Validates `uptime.ps1` |
| `php scripts/test_php_version.php` | Validates `php_version.ps1` |
| `php scripts/test_php_extensions.php` | Validates `php_extensions.ps1` |
| `php scripts/test_php_ini_values.php` | Validates `php_ini_values.ps1` |
| `php scripts/test_mysql_status.php` | Validates `mysql_status.ps1` |
| `php scripts/test_mysql_version.php` | Validates `mysql_version.ps1` |
| `php scripts/test_mysql_databases.php` | Validates `mysql_databases.ps1` |
| `php scripts/test_mysql_size.php` | Validates `mysql_size.ps1` |

Run `verify_system_status.php` when changing `modules/system_status/`, `scripts/system_status_api.php`, `includes/itm_system_status_native.php`, `includes/itm_system_status_powershell.php`, `includes/itm_system_status_storage.php`, `includes/itm_system_status_cache.php`, `db/` `system_status`, or any `includes/*.ps1` metrics script. On large tenants the storage tree scan and `information_schema` queries can be slow — run Refresh from CLI or raise PHP `max_execution_time` in browser if needed. API dispatcher: `scripts/system_status_api.php?action=…` (Admin only; invalid action → HTTP 400). Module UI: `modules/system_status/index.php` — tabs read cached JSON from `system_status`; **Refresh** POST runs `itm_system_status_refresh_all()`. Sub Storage parent nodes sum child totals plus direct files in each folder. For README screenshots see **Roles & Permissions scripts** above (`take_screenshots_modules.py`).

### Resignations, employees, and fields_missing audits

**Catalog (What / How / Access):** `scripts/scripts.php` — Database section rows for `debug_resignations_termination_date.php`, `verify_employee_type_resignations.php`, `employee_fields_missing.php`, `fields_missing.php`, `fields_missing_reviewed.php`.

**When to run**

- `debug_resignations_termination_date.php` — known `termination_date` missing from weekly report or `Incorrect DATE value: '0000-00-00'` on prepare.
- `verify_employee_type_resignations.php` — after `employee_type`, `resignations`, or `employees` termination/type schema or filter changes.
- `employee_fields_missing.php` or `fields_missing.php --module=employees` — after `db/` `employees` columns or profile/list UI changes.
- `fields_missing.php` — after table column or scaffold/bespoke list UI changes; use `--strict-gate` in CI to fail on unreviewed bespoke `[SKIP][fail]` lines.
- `fields_missing_reviewed.php` — after editing `scripts/data/fields_missing_reviewed.json`.

**MySQL 8 date SQL:** resignations queries must not use the literal `'0000-00-00'` in WHERE clauses (`Incorrect DATE value` under `NO_ZERO_DATE`). Use `itm_sql_valid_date_predicate('e.termination_date')` from `includes/itm_date_format.php` instead.

#### `fields_missing` reviewed exceptions (`scripts/data/fields_missing_reviewed.json`)

Use this registry when a bespoke module **intentionally** fails a gated UI check (Search, Sort, Pagination, list heading layout, …) and the team has reviewed the gap. Matching failures still run every audit; output changes from `[SKIP][fail]` to **`[SKIP][fail][reviewed]`** only. Exit code and `failure_count` are unchanged (`[SKIP][fail]` remains informational). Browser audit output keeps the module slug as plain text on `[SKIP][fail][reviewed]` lines (no `modules/<slug>/index.php` link).

| Field | Required | Purpose |
|-------|----------|---------|
| `version` | yes | Schema version (`1`) |
| `description` | no | Registry purpose (shown in manifest) |
| `modules.<slug>.reviewed_at` | no | Review date (`YYYY-MM-DD`) |
| `modules.<slug>.reason` | recommended | Why the module is exempt from that gate |
| `modules.<slug>.checks[]` | yes | One object per reviewed failure |
| `checks[].check` | recommended | Gate label (`Search`, `Sort`, `Pagination`, …) — matched against `{slug} bespoke gate: {label} NOT OK` |
| `checks[].code` | recommended | Failure code from report (`bespoke_list_ui_search`, …) — preferred match key |
| `checks[].note` | no | Copy of audit detail for reviewers |

**Workflow:** reproduce with `php scripts/fields_missing.php --module=<slug>` → add rows to JSON → validate `php scripts/fields_missing_reviewed.php` → update module `AGENT_NOTES.md` → re-run `fields_missing.php` and confirm `[SKIP][fail][reviewed]` lines.

**Strict gate:** `php scripts/fields_missing.php --strict-gate` (browser: `?strict_gate=1`) exits `1` when any bespoke `[SKIP][fail]` line is **not** in the registry. Reviewed `[SKIP][fail][reviewed]` lines do not fail strict gate. Use in CI when bespoke gate hygiene must be enforced.

#### CRUD view/edit field parity (`check_crud_view_edit_field_parity.php`)

**Catalog (What / How / Access):** `scripts/scripts.php` — Codebase row for `check_crud_view_edit_field_parity.php`.

Static audit for flattened CRUD modules that render detail rows from `$viewColumns` but build create/edit from `$uiColumns`, `$formUiColumns`, `$formColumns`, or `*_edit_form_sections()` cards. Catches the Room Types class of bug: list grid hides columns via `$*ListHidden` / `$*ListHiddenFields` on `$uiColumns` while view still shows them — create/edit must re-expose those fields (minus `company_id` and audit meta).

- **CLI:** `php scripts/check_crud_view_edit_field_parity.php` · optional `--module=<slug>` · `--json`
- **Browser:** [check_crud_view_edit_field_parity.php?run=1](http://localhost/it-management/scripts/check_crud_view_edit_field_parity.php?run=1) (Admin session)
- **When to run:** after changing `$uiColumns` / `$viewColumns` filters, list-hidden field arrays, or bespoke edit-section helpers under `modules/*/includes/`
- **Shared lib:** `scripts/lib/itm_crud_view_edit_field_parity_audit.php`
- **Exit `1`:** when internal view/edit parity gaps exist (JSON `failure_count` / `failures`)
- **Plain output:** tag-free per-scope reference gap lines — `{module}.{column}: schema column not referenced in index|create|edit|view|list_all|includes PHP` — when the column name is not a quoted literal **and** not covered by that scope’s dynamic `$uiColumns` / `$displayFieldColumns` / `$viewColumns` / `$formUiColumns` loop (thin wrappers inherit `index.php` for create/edit/view/list_all). **No line** when the column is intentionally omitted from that scope (`$*ListHidden`, `$*ListOnlyHidden` on list only, audit list/form hiders, hidden `company_id`). **`includes`** scope is skipped when the column is already referenced in index/create/edit/view/list_all. **`create` / `edit`** scopes are skipped when `modules/{slug}/create.php` or `edit.php` is absent (read-only modules such as `attempts`). Create/edit scopes that use `$formUiColumns` do not treat `$uiColumns` list-hidden fields as reference gaps when those fields are merged back onto the form. Read-only modules (no `create.php` and no `edit.php`) also skip view/edit parity checks. **Browser colour:** parity failure lines, related `list-hidden from $uiColumns only` notes (when that module has failures), and the summary failure count use `colorText(..., 'fail')` (red in browser, ANSI in CLI). Summary reports registry/audited/skipped module counts — not all MySQL tables. Omits `includes` scope when `modules/{slug}/includes/` is absent. Browser links module slug to `../modules/{slug}/`; CLI gap lines use `{slug}.{column}` and print the module folder URL on its own line when `--module=` is set.

**Seeded modules:** `backup_tape_log` — Search, Sort, Pagination (monthly grid bespoke UI). `company_module_access` — Sort, Pagination (admin matrix UI; Search and Import Excel implemented on `index.php`). `share_modules` — Sort, Pagination (admin share matrix UI; Search and Import Excel implemented on `index.php`).

#### `ui_configuration` reviewed exceptions (`scripts/data/ui_configuration_reviewed.json`)

Use this registry when a **gate-excluded** module (listed in `ui_configuration_excluded_modules.txt` or matching an excluded prefix) intentionally prints `[n/a][pass]`, `[n/a][fail]`, or `[n/a][n/a]` for a UI configuration check and the team has reviewed the gap. Matching lines add **`[reviewed]`** on the per-line output (for example `[n/a][fail][reviewed]`). The end-of-run **Gate-excluded modules with contract failures** footer lists the same failures and appends **`[reviewed]`** on each bullet when registered. Exit code is unchanged — gate-excluded lines remain informational.

| Field | Required | Purpose |
|-------|----------|---------|
| `version` | yes | Schema version (`1`) |
| `description` | no | Registry purpose (shown in manifest) |
| `modules.<slug>.reviewed_at` | no | Review date (`YYYY-MM-DD`) |
| `modules.<slug>.reason` | recommended | Why the module is exempt from that check |
| `modules.<slug>.checks[]` | yes | One object per reviewed line |
| `checks[].check` | recommended | Exact label from `check_ui_configuration_coverage.php` (`+ New Button`, `Search`, `Create entry (create.php)`, …) |
| `checks[].code` | recommended | Stable match key (`ui_config_new_button`, …) |
| `checks[].note` | no | Copy of audit detail for reviewers |

**Prefix keys:** module map keys ending in `*` (for example `is_*`) apply to every gate-excluded slug whose folder name starts with the prefix before `*`. Exact slug keys still match one module only.

**Workflow:** reproduce with `php scripts/check_ui_configuration_coverage.php` → add rows to JSON → validate `php scripts/ui_configuration_reviewed.php` → update module `AGENT_NOTES.md` → re-run coverage and confirm `[reviewed]` suffix.

**Seeded modules:** `attempts` — no `create.php` / `edit.php` (read-only security log). `audit_logs` — immutable read-only trail (`create.php` redirect; no edit/delete entry files). `backup_tape_log` — monthly grid bespoke UI (no `create.php`; Search, Sort, Pagination, bulk toolbar, and create/new-button checks reviewed). `birthdays` — read-only monthly list (`index.php` only; no Actions column, pagination, CRUD entry files, or bulk delete). `budget_report` — computed finance summary (`index.php` only; Search/Sort implemented; pagination, Actions, CRUD entry files, bulk delete, and new-button checks reviewed). `calendar` — aggregated grid (`index.php` only; create/view link to source modules; list-contract checks reviewed). `company_module_access` — admin matrix bespoke UI (Search/Import Excel implemented; Sort, Pagination, and bulk-delete toolbar checks reviewed). `contacts` — department-grouped directory (`index.php` only; Search implemented; Sort, Pagination, CRUD entry files, and bulk-delete checks reviewed). `idfs` — bespoke dashboard (`create.php`/`edit.php` redirect to inline forms on `index.php`; Back & Save and new-button checks reviewed). `import` — admin bulk-import wizard (`index.php` only; Search, Sort, Pagination, CRUD entry files, bulk delete, and new-button checks reviewed). `is_*` — equipment-type façades (`modules/is_switch`, `is_server`, …) delegate to `modules/equipment/` (no local list table; Search, Sort, Pagination, Actions, export, bulk, and new-button checks reviewed). `myactivity` — employee-scoped read-only audit timeline (`view.php` only; Search and sortable list columns; all list-contract lines reviewed). `news` — multi-source feed reader (`index.php` only; gate-excluded; column sort + reviewed Search/Pagination/CRUD entry lines). `ops_report` — daily report bespoke UI (`create.php` wrapper; Search and cross-date hit pagination implemented; + New Button, main-grid column sort, new-button position/style, and bulk-delete checks reviewed). `org_chart` — interactive tree (`index.php` only; drag-and-drop hierarchy AJAX; all list-contract and CRUD entry checks reviewed). `passwords` — vault manager (`index.php` modals; Search/Sort/Pagination and bulk delete on entry list; no create.php/edit.php/list_all.php and scaffold new-button checks reviewed). `private_contacts` — vault address book (inline delete via `index_logic.php`; no delete.php/list_all.php or bulk-delete toolbar checks reviewed). `reports` — read-only Chart.js dashboard (`index.php` only; aggregates existing tables via `api/helpers.php`; all list-contract and CRUD entry checks reviewed). `resignations` — read-only weekly report (`index.php` only; Search/Sort implemented; pagination, Actions, CRUD entry files, bulk delete, and new-button checks reviewed). `settings` — configuration dashboard (`index.php` only; SideMenu + All Backups bespoke tables; Search, Sort, Pagination, bulk toolbar, and CRUD entry checks reviewed). `system_status` — admin diagnostic tabbed dashboard (`index.php` only; no list table; table Actions, export, Search, Sort, Pagination, bulk toolbar, CRUD entry files, and new-button checks reviewed). `todo` — Microsoft To-Do task cards (`index.php` only; Search/Sort/Pagination and Settings UI list header implemented; Table Actions and bulk-delete toolbar checks reviewed). `webmail` — session mailbox (`compose.php`, folder tabs, read-pane `view.php`; gate-excluded; **Back & Save** on create/edit redirect stubs reviewed).

### Named module verifiers (catalog: `scripts/scripts.php`)

Run after changes to modules that previously relied only on MBQA/PHPUnit/repro scripts:

- `php scripts/verify_rack_planner.php` — `modules/rack_planner/` price source sync
- `php scripts/verify_bookmarks_import.php` — `modules/bookmarks/import.php` HTML folder paths, duplicate URL skips (no orphan folders), disposable script employee + `scripts/data/bookmarks_import_sample.html`
- `php scripts/verify_bookmarks_folder_move.php` — bookmarks folder move/merge (`bkm_move_folder()`, duplicate same-name sibling prompt)
- `php scripts/verify_notes_vault.php` — notes private-field vault encryption (`notes_vault_helpers.php`, shared vs private persistence, label_hash); browser catalog uses `itm_script_output_nl()` / `itm_script_format_status_line()` (not `fwrite(STDOUT)`).
- `php scripts/verify_todo_vault.php` — todo private-field vault encryption (`todo_vault_helpers.php`, shared vs private persistence, `title_hash`)
- `php scripts/verify_events_vault.php` — events private-field vault encryption (`events_vault_helpers.php`, shared vs private persistence, hydrate when vault locked/unlocked); browser catalog uses `itm_script_output_nl()` / `itm_script_format_status_line()` (not `fwrite(STDOUT)`).
- `php scripts/verify_notes_share.php` — notes temporary QR/code share sessions (`note_share_sessions`, `join.php`, `notes_share_helpers.php`)
- `php scripts/verify_private_contacts_vault.php` — private contacts vault encryption (`pc_vault_helpers.php`, list hydrate/search, master-key re-encrypt); browser catalog uses `itm_script_output_nl()` / `itm_script_format_status_line()` (not `fwrite(STDOUT)`).
- `php scripts/verify_qr_share_modules.php` — Passwords, Bookmarks, Todo, Events, Private Contacts, Explorer, Floor Plans, Rack Planner, and CRUD record share (`departments` via `includes/itm_crud_record_share.php`) temporary QR/code sessions (`share_sessions`, `join.php`, module `*_share_helpers.php`, shared `includes/itm_qr_share.php`). Inventory: `docs/CRUD_RECORD_SHARE.md`.
- `php scripts/verify_qr_share_join_hardening.php` — Public join hardening: eight-digit codes, token-only vault/file modules, per-IP join rate limit (`includes/itm_qr_share.php`)
- `php scripts/verify_qr.php` — `modules/qr/` QR Generator: `qr_codes` / `qr_code_scans`, payload builders, scan recording, type catalog, short-url bidirectional probe when `short_urls` exists
- `php scripts/verify_short_url.php` — `modules/short-url/`: tables, root `go.php` stub, validation, click analytics, password hash, linked QR, `create_from_destination`, expiry detection
- `php scripts/verify_attempts_view_rbac.php` — `modules/attempts/view.php` enforces `can_view` RBAC for User roles without Attempts permission
- `php scripts/verify_emails_view_rbac.php` — `modules/emails/view.php` enforces `can_view` RBAC for User roles without Email Management permission
- `php scripts/verify_module_share.php` — `company_module_share` opt-out matrix + `has_module_share_access()`; requires `share_sessions` table
- `php scripts/migrate.php --status` / `--apply` — `db/migrations/*.sql` runner + `schema_migrations` history; schema-satisfied migrations (fresh `db/` import) are not false PENDING (`includes/itm_database_migrations.php`)
- `php scripts/verify_db_migrations.php` — each `db/migrations/*.sql` vs live schema/data (Applied / Superseded / Not applied)
- `php scripts/verify_bigint_table_review.php` — BIGINT migration review tables: row counts, max IDs, column types, AUTO_INCREMENT; browser `?run=1` + coloured report shell (Administrator); static 300×5 scale projection; pair with `db/migrations/audit_logs_bigint.sql`
- `php scripts/verify_whatsapp_share.php` — WhatsApp deep-link message/url helpers (`includes/itm_whatsapp_share.php`, `js/itm-whatsapp-share.js`); browser catalog uses `itm_script_output_nl()` / `itm_script_format_status_line()` (not `fwrite(STDOUT)`).
- `php scripts/verify_outlook_share.php` — Outlook/mail compose helpers (`includes/itm_outlook_share.php`, `js/itm-outlook-share.js`)
- `php scripts/verify_request_password.php` — `modules/request_password/` workflow + delete guard
- `php scripts/verify_chatbot.php` — `js/chatbot.js`, `chat_api.php`, `knowledge_base` tenant scope
- `php scripts/verify_command_palette_search.php` — `includes/itm_command_palette_search.php`, `includes/itm_search_index.php`, `modules/search/api.php`, `js/command-palette.js`, header Ctrl+K wiring, RBAC gates, FULLTEXT backfill/remove probes
- `php scripts/verify_command_palette_sidebar_slugs.php` — every Admin sidebar module slug findable via palette module navigation; lib `scripts/lib/itm_command_palette_sidebar_verify.php`
- `php scripts/apply_search_index_backfill.php` — dry-run default; `--apply` / `?apply=1` syncs `search_index` for palette modules per company (`--company=`, `--module=`). Canonical population path — **not** Add sample data / `db/02_data_sample.sql`.
- `php scripts/verify_appointment.php` — `modules/appointments/`, `includes/itm_appointment.php`, appointment `db/` bundle
- `php scripts/verify_appointment_settings.php` — `modules/appointment_settings/`, `includes/itm_appointment_settings_admin.php`
- `php scripts/verify_live_chat.php` — Live Chat schema, SLA, ACL, notifications, ticket activity/comments helpers
- `php scripts/verify_ticket_productivity.php` — Ticket canned responses, merge, CSAT token/public URL, merge smoke
- `php scripts/verify_ticket_surveys.php` — Ticket questionnaires/surveys: seeds, issue/submit, activity, webhooks, automation, merge tag/cancel, stats (`docs/TICKET_SURVEYS.md`)
- `php scripts/verify_automation_rules.php` — Workflow automation: tables, audit triggers, seed rule, `itm_automation_rules_run_rule()` by id, `automation_rule_runs` success row
- `php scripts/run_automation_rules.php` — Cron: date-based triggers (`equipment.warranty_expiring` within 30 days)
- `php scripts/debug_peer_options.php` — Debug Chat-with peer picker: `it_settings.chat_same_tenant`, accessible companies, merged peer options vs `list_employees` (CLI `--company_id=` `--employee_id=`; browser query params)

### Performance benchmarks

| Script | Purpose |
|--------|---------|
| `php scripts/benchmark_stats_optimized.php` | Benchmark for `user-config.php` stats: same filters via `includes/itm_user_config_stats.php` — loop of 31 COUNT queries vs 1 consolidated query; exits non-zero on mismatch or if batch is not faster. |
| `php scripts/benchmark_user_config.php` | Benchmark for redundant alerts/events queries removed from `user-config.php`: legacy 4-query loop vs production full batch + `itm_user_config_extract_alerts_events_counts()`; exits non-zero on mismatch or if extract is not faster than legacy loop. |
| `php scripts/idf_device_port_sort_test.php` | Regression test for IDF device port list sorting (copper before fiber). |
| `php scripts/crud_tables.php` | Lists each module’s first <code>$crud_table</code> line in <code>index.php</code>. Bespoke/exception modules without that assignment are <strong>Skip</strong> (see <code>docs/list_bespoke_UI.txt</code> + <code>scripts/data/crud_tables_skip_modules.txt</code>) — not Missing. No database table checks. |
| `php scripts/crud_titles.php` | Lists each module’s first <code>$crud_title</code> line in <code>index.php</code>. <code>is_*</code> shortcuts and bespoke modules without it are <strong>Skip</strong> (see <code>itm_crud_titles_should_skip_module()</code>).
| `php scripts/crud_actions.php` | Audits module-to-action mapping by reading each module entry file (`index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) for `$crud_action` assignments. Non-standard CRUD modules with no assignment are <strong>Skip</strong> (see <code>itm_crud_actions_should_skip_module()</code>).
| `php scripts/verify_port_visualizer_layout.php` | CLI regression for IDF port visualizer Vertical vs Horizontal grid metadata and port 2 placement.
| `php scripts/test_visualizer_v2.php` | Browser/CLI mock for IDF port visualizer Vertical vs Horizontal layouts (48-port demo). Regression: `php scripts/verify_port_visualizer_layout.php`. |

Catalog rows for the scripts above: **`scripts/scripts.php`** (UI &amp; modules / IDF sections). Do not duplicate What/How tables here.

#### Full scripts test matrix (`scripts/SCRIPTS_TEST_MATRIX.md`)

Canonical map of **all cataloged** `scripts/scripts.php` entries into execution tiers (0 docs, 1 CI baseline, 2 static `check_*`, 3 runtime `verify_*`/`repro_*`, 4 MBQA/human-flow, 5 excluded destructive/maintenance).

| Artifact | Purpose |
|----------|---------|
| `scripts/SCRIPTS_TEST_MATRIX.md` | Tier counts, runner coverage map, command batches, full catalog classification table, Tier 5 exclusion summary |
| `scripts/data/scripts-matrix-destroy-log.md` | Append-only log when a script forces a fresh DB clone |
| `scripts/data/scripts_errors.txt` | Latest safe-matrix run report (A–Z Passed / Skipped / Excluded / Covered + Failures root cause) |

**Do not** use `perform_audit.php` as a blanket quality gate. It scans Tier 1–3 CLI scripts only (skips Tier 4 MBQA, Tier 5 maintenance, `repro_*`, `verify_*`, `_tmp_*`, `health.php`, and session-mock harnesses `test_ajax.php` / `test_edit.php`). Allowlisted intentional exit codes live in `scripts/data/perform_audit_allowlist.json`. Prefer Tier 1 runners first, then Tier 2/3 batches, then Tier 4 on a healthy clone; run `repro_*` / `verify_*` individually when needed.

**Destroy -> document -> fresh clone:** if a script wrecks `itmanagement` or critical trees, record the culprit in `scripts/data/scripts-matrix-destroy-log.md` (status `DESTROYED_ENV`), re-import `db/` (`bash scripts/verify_database_sql_import.sh`) or split bundle (`bash scripts/import_database_split.sh`), sanity-check, then resume. Full protocol lives in `SCRIPTS_TEST_MATRIX.md`.

When adding a catalog row, update `SCRIPTS_TEST_MATRIX.md` in the same PR.

#### 5. Pre-merge verification (scripts)

When adding or changing anything under `scripts/`:

1. Confirm a row exists in **`scripts/scripts.php`** (what / how / access) — do not mirror that row in `SCRIPTS.md`.
2. Open the script in the **browser** (if applicable) — **← Scripts index** visible; module names use `../modules/…`; table names link only when a matching module folder exists; no phpMyAdmin links outside `scripts/scripts.php`.
3. Run **`php -l scripts/<changed>.php`** on touched PHP files.
4. Run the script’s CLI command once when behavior is non-trivial.

**Tenant unique keys (`db/`):** after changing `CREATE TABLE` uniques or exempt skips in `includes/database_sql_unique_audit.php`, run `php scripts/check_database_sql_company_name_uniques.php`. Intentional skips include `bookmark_folders` / `bookmarks` (duplicate display names allowed), `floor_plan_item_tags` (junction identity is `PRIMARY KEY (floor_plan_id, tag_id)` only — never add `UNIQUE (company_id, floor_plan_id)`), `events` (duplicate/encrypted titles — never add `UNIQUE (company_id, title)`), and all `*_share_sessions` tables (ephemeral QR snapshots — `UNIQUE (access_token)` only; never add `UNIQUE (company_id, employee_id)`). Module notes: `modules/bookmarks/`, `modules/bookmark_folders/`, `modules/floor_plans/`, `modules/events/`, `includes/itm_qr_share.php`.

**Index table compliance:** after changing list-table import/Actions markers or bespoke index UIs, run `php scripts/check_index_table_compliance.php`. `data-itm-no-import-excel="1"` means Import Excel / `data-itm-db-import-endpoint` is not required (e.g. `backup_tape_log`, `birthdays`, `contacts`). Actions `data-itm-actions-origin` / `itm-actions-cell` are required only when an Actions column exists. Browser report HTML-escapes lines inside `<pre>`.

**Company column (`company_id`) inventory:** after changing `$hideCompanyIdTables`, flattened list column filters, or bespoke modules that may show tenant company on list tables, run `php scripts/check_company_id_ui_column.php`. Static scan of every `modules/{slug}/**/*.php` file plus `db/01_schema.sql` (no database). Browser report links each module slug to `../modules/{slug}/index.php` (relative, new tab). Default exit `0` (report). `--list-exposed` prints exposed slugs only; `--strict` exits `1` when scaffold modules omit their table from `$hideCompanyIdTables` on any scaffold entry file. Lib: `scripts/lib/itm_company_id_ui_column_audit.php`. Bulk repair: `php scripts/apply_hide_company_id_tables.php --module=slug` and/or `--prefix=hotel_` (both repeatable) then `--apply` (browser [apply_hide_company_id_tables.php?run=1&apply=1](http://localhost/it-management/scripts/apply_hide_company_id_tables.php?run=1&apply=1), Admin session).

**CRUD `$hasCompany` vs `$fieldColumns`:** after changing flattened CRUD init blocks, `cr_is_hidden_*` field filters, or Add sample data gates, run `php scripts/check_crud_has_company_from_field_columns.php`. **FAIL** when a module hides `company_id` from `$fieldColumns` (via module-specific `cr_is_hidden_*` or an inline `$fieldColumns` filter) **before** the `$hasCompany` loop still reads `$fieldColumns` — same root cause as the employee_notifications empty inbox / missing sample-data button. **WARN** when `$hasCompany` still uses the legacy `foreach ($fieldColumns …)` pattern; optional `--strict-warn` / `?strict_warn=1` fails those too. Lib: `scripts/lib/itm_crud_has_company_field_columns_audit.php`. Browser: [check_crud_has_company_from_field_columns.php?run=1](http://localhost/it-management/scripts/check_crud_has_company_from_field_columns.php?run=1) (Admin session).

**CRUD boolean list/view cells:** after changing `cr_render_cell_value()` or adding `tinyint(1)` columns, run `php scripts/check_crud_boolean_cell_display.php`. **FAIL** when a visible list/view `tinyint(1)` column (from `db/01_schema.sql`) lacks display handling — **`active`** must use Active/Inactive badges; other checkbox columns must call `itm_crud_render_checkbox_boolean_cell_value()`, use bespoke ✅/❌, or render `badge-success` / `badge-danger` labels (examples: `employee_roles.sidebar_show` Show/Hide; `employee_sidebar_preferences` `in_array($field, ['active', 'is_visible'])` badge block). **`list_all.php` / `view.php`** duplicate scaffolds inherit PASS from sibling **`index.php`** when the canonical renderer already handles the column. Scans `modules/*/index.php`, `view.php`, and `list_all.php`. Lib: `scripts/lib/itm_crud_boolean_cell_display_audit.php`. Browser: [check_crud_boolean_cell_display.php?run=1](http://localhost/it-management/scripts/check_crud_boolean_cell_display.php?run=1) (Admin session).

**UI configuration coverage:** after changing flattened list UI (table actions, export card, search/sort/pagination, bulk toolbar, CRUD entry wrappers) or gate-exclusion lists, run `php scripts/check_ui_configuration_coverage.php`. Gated modules print `[pass]` / `[fail]` / `[n/a]`; gate-excluded slugs still appear as `[n/a][pass]`, `[n/a][fail]`, or `[n/a][n/a]` with optional `[reviewed]` when listed in `scripts/data/ui_configuration_reviewed.json` (manifest: `ui_configuration_reviewed.php`). Reviewed gate-excluded failures show `[reviewed]` on the per-line output and append `[reviewed]` on matching footer bullets. Thin-router modules (`ip_addresses`, `ip_subnets`, `rack_planner`) merge `includes/partials/render.php` via `itm_ui_merge_thin_router_audit_content()` so audits match runtime markup. Within each gate-excluded module, lines sort as `[n/a][fail]` → `[n/a][n/a]` → `[n/a][pass]` (stable within each bucket). Exit `2` only when a gated module has `[fail]`. Optional `--list-excluded` (CLI) or `?list_excluded=1` (browser) prints gate-excluded slug names in the header.

**List search FK labels:** after changing flattened CRUD list search, FK display, bespoke module search (`switch_ports`, `todo`, `notes`, `private_contacts`, `ip_subnets`, `ip_addresses`, `bookmarks`, `passwords`, `visitors_access_log`), or adding a new searchable module:

```bash
php scripts/apply_crud_fk_label_search.php
php scripts/check_fk_label_search_coverage.php
php scripts/verify_crud_fk_label_search.php
php scripts/verify_employees_equipment_search_coverage.php
```

When scaffolding new flattened modules, run `php scripts/apply_crud_fk_label_search.php` if the search block omits `itm_crud_fk_label_search_conditions()`. Bespoke modules whose visible list columns are scalar text/datetime only (no FK labels) may use `itm_crud_scalar_column_search_conditions()` from `includes/itm_crud_scalar_column_search.php` instead — recognized by the static audit. The static audit (`check_fk_label_search_coverage.php`) is smoke step 4 and uses **universal pass rules only** (no per-module N/A allowlist); runtime verify runs in the **database-import** CI job. **`verify_crud_fk_label_search.php` PASS** for todo/notes/bookmarks: seeded row title appears in isolated list HTML when searching FK/share/folder labels (todo/notes/bookmarks probes unlock vault with `hash('sha256', 'Admin')` in the subprocess session). Run `php scripts/verify_employees_equipment_search_coverage.php` after employees or equipment list search / FK label helper changes.

**List/view FK display labels:** after changing flattened CRUD list/view rendering or adding modules with FK columns:

```bash
php scripts/apply_crud_fk_label_display.php
php scripts/list_raw_columns.php
```

When scaffolding new flattened modules, run `php scripts/apply_crud_fk_label_display.php --apply` if `cr_render_cell_value()` omits the `$GLOBALS['fkMap'][$field]` branch (canonical pattern: `modules/license_management/index.php`). Bespoke modules with alternate render signatures must be refactored to the same contract — not audit exemptions. Exit `0` on `php scripts/list_raw_columns.php` (optional live check: `--only-repro --company=1`).

**Script browser nav (no duplicate ← Scripts index):** after changing `scripts/lib/script_cli_output.php`, `scripts/lib/script_browser_nav.php`, or any browser HTML shell under `scripts/*.php`, run:

```bash
php scripts/check_script_browser_nav_duplicate.php
```

Exit `0` when no file stacks `itm_script_browser_nav_echo()` / `itm_script_browser_nav_html()` on top of `itm_script_output_begin()` in the same browser path.

**Catalog table tags:** after changing `scripts/scripts.php` catalog rows or script SQL that affects table usage, run:

```bash
php scripts/apply_script_catalog_tags.php --apply
php scripts/check_script_catalog_tags.php
```

**Environment variables (`.env.example` drift):** after changing `.env.example`, `config/config.php`, or integration `getenv()` reads, run:

```bash
php scripts/check_env_vars_in_use.php
php scripts/check_env_vars_in_use.php --strict   # fail on example-only or undocumented app keys
```

Scans PHP (`getenv`, `$_ENV`, `foreach ([…] as $x) { getenv($x) }` alias lists), Python (`os.environ`), and shell (`${VAR}`) under the repo (excludes `vendor/`, `phpunit/coverage/`, `qa-reports/`). Shared lib: `scripts/lib/itm_env_vars_audit.php`. JSON: `--json` / `?json=1`.

**UI action emoji (NO MIXED):** after any change to buttons, links, form actions, modals, or page headings (`<h1>`–`<h3>`), run:

```bash
php scripts/check_ui_action_emoji.php   # 0 violations incl. mixed emoji+word
php -l includes/itm_ui_action_labels.php
bash scripts/smoke_test.sh
```

**Codacy XSS echo (module PHP):** after adding/changing search inputs, dynamic `href` query strings, or other echoed request-derived values in `modules/`, run:

```bash
php scripts/check_codacy_xss_echo.php
php scripts/check_codacy_xss_echo.php --strict   # fail when violations remain
```

Catches patterns Codacy flags as XSS: short-echo `<?= sanitize($search…)` in `value` / `href` / `<strong>`, and `echo sanitize(http_build_query(…))` inside `href`. Fix with `<?php echo sanitize(…); ?>` for search fields and pre-assign `htmlspecialchars('index.php?' . http_build_query(…), ENT_QUOTES, 'UTF-8')` for hrefs. Same-line `itm-codacy-xss-exempt:` comment for intentional legacy lines. Default run is informational (exit `0`); `--strict` exits `1`.

**Unary not-operator [Warning] (application PHP):** when reviewing Codacy `Operator ! prohibited` findings in `modules/`, `includes/`, or `config/` (optional `scripts/` with `--include-scripts`), run:

```bash
php scripts/check_not_operator.php
php scripts/check_not_operator.php --include-scripts
```

Flags unary `!` on `$variables` (for example `if (!$ok)`). **Warning tier only — always exit `0`**. Many hits are intentional falsy checks (`null`, `0`, mysqli handles); do **not** bulk-replace with `=== false`. Excludes `!function_exists()`, `!is_array()`, `!preg_match()`, and `!==`. Fix case-by-case or add same-line `itm-not-operator-exempt:` with reason. No `--strict` mode.

**Manual SQL strings (module PHP):** after adding/changing dynamic SQL built via string concatenation or interpolation in `modules/` (or `scripts/` with `--include-scripts`), run:

```bash
php scripts/check_manual_sql_string.php
php scripts/check_manual_sql_string.php --strict   # fail when violations remain
php scripts/check_manual_sql_string.php --include-scripts
```

Catches real manual SQL construction (`"SELECT … " . $var`, `"INSERT … {$user}"`, `mysqli_query` / `itm_run_query` with user input in the SQL string). Excludes URL `http_build_query()` / `.php?` href patterns (static-tool false positives), `mysqli_prepare` + `?` placeholders, `itm_run_query($conn, $sql)` pass-through, and scaffold `cr_escape_identifier()` table names without user input. Same-line `itm-manual-sql-exempt:` for intentional legacy lines. Default run is informational (exit `0`); `--strict` exits `1`. Complements `check_sql_injection_coverage.php` (proximity audit for unbound queries near `$_GET` / `$_POST`).

**UTF-8 / mojibake:** after copy/paste from Excel or editors that mis-save encoding, or when UI shows corrupted emoji instead of the intended symbol:

```bash
php scripts/verify_source_utf8_mojibake.php
php scripts/verify_source_utf8_mojibake.php --path=modules/patches_updates
php scripts/fix_source_utf8_mojibake.php --path=modules/patches_updates
php scripts/fix_source_utf8_mojibake.php --files=modules/patches_updates/create.php --apply
```

Browser repair uses **selection mode** on `scripts/fix_source_utf8_mojibake.php` (Select to Fix → check files → Preview Selected / Fix Selected). Bulk all-files repair: `scripts/apply_utf8_mojibake_fix.php` with `--apply` / `?apply=1`.

**NO MIXED patterns** (hard fail — emoji immediately followed by action word on interactive controls/headings):

| Pattern | Examples |
|---------|----------|
| `💾\s*Save` | `💾 Save`, `💾 Save Changes` |
| `🔙\s*Back` | `🔙 Back` |
| `🔙\s*Cancel` | `🔙 Cancel` |
| `✏️\s*Edit` | `✏️ Edit`, `✏️ Edit Folder` |
| `🗑️\s*Delete` | `🗑️ Delete` |
| `➕\s*(Create\|New\|Add)` | `➕ New Task`, `➕ Add Bookmark` |
| `🔎\s*View` | `🔎 View Ticket Details` |
| `◀️\s*Previous` | `◀️ Previous`, `title="◀️ Previous"` |
| `▶️\s*Next` | `▶️ Next`, `title="▶️ Next"` |

**Known literals:** View Ticket Details, Edit Ticket, New Equipment, Create IDF, Edit IDF, View Employee System Access.

**Pagination emoji map (visible → `title`):** ⏮️ First page · ◀️ Previous page · ▶️ Next page · ⏭️ Last page. Standard list footers require all four page-step icons when navigation renders. Helpers: `itm_ui_pagination_emoji()` / `itm_ui_pagination_title()` in `includes/itm_ui_action_labels.php`.

**Exemptions:** bulk `data-itm-bulk-cancel="1"` visible `Cancel`; bulk Select to Delete / Clear Table; submit Search; descriptive non-actions (View IP record, Reset View, etc.); same-line `itm-ui-action-exempt:` comment. **Pagination is not exempt:** list anchors must use emoji-only visible text (`⏮️` / `◀️` / `▶️` / `⏭️`) with `title="First page"` … `title="Last page"` — plain `Previous` / `Next` or mixed `title="▶️ Next"` fail `itm_check_pagination_nav_titles()`, `check_pagination_emoji.php`, and `check_ui_action_emoji.php`. **Search reset is not exempt:** list contract requires emoji-only `🔙` on `<a>` (plain `Clear` fails `itm_check_search`).

**Verify:** `php scripts/check_pagination_emoji.php` — exit `0` when every audited module source satisfies the pagination emoji contract (thin-router merge + tab/partial entry files).

**Bulk fix:** `php scripts/apply_ui_action_emoji.php` — **Browser + CLI**, dry-run default; `--apply` / `?apply=1` writes; lists changed files. List pagination legacy labels: `php scripts/apply_pagination_emoji_labels.php` (same dry-run / `--apply` contract). First/last page controls: `php scripts/apply_pagination_first_last.php` (same dry-run / `--apply` contract; includes bespoke URL builders for tickets, catalogs, IPAM, passwords, bookmarks, audit logs, system access, ops_report search hits, emails send logs). PHP ternary h1, idfs h3, and JS modal innerHTML still need manual edits.

**Head favicon:** `php scripts/apply_head_favicon_link.php` — inserts `<?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>` after `<title>` in module `index.php`, `create.php`, `edit.php`, `view.php`, and standalone `list_all.php` / `view_all.php` files that have `<head>` but no Settings-backed favicon helper (dry-run default; `--apply` / `?apply=1` writes). Replaces legacy Settings/explorer hardcoded `<link rel="icon">` blocks with the shared helper.

**Browser title module emoji:** `php scripts/apply_crud_browser_title_module_icon.php` — injects `itm_crud_apply_module_icon_to_browser_title()` before canonical `<title><?= sanitize($crud_title) ?>…` in `modules/**/*.php` (dry-run default; `--apply` / `?apply=1` writes). Helper: `includes/itm_crud_browser_title.php`. Gate: `php scripts/check_crud_browser_title_module_icon.php` (exit `1` when canonical title exists without the helper). `titles_list.php` summary notes that emoji is applied at runtime to `$crud_title`, not in the static tag.

**Page chrome verify:** `php scripts/verify_module_page_chrome.php` — cross-checks `modules/{slug}/index.php`, `create.php`, `edit.php`, `view.php`, and standalone `list_all.php` with a `<head>` for canonical browser `<title>` (`titles_list.php` / `itm_titles_list_audit.php`) and Settings favicon wiring (`itm_check_module_favicon_link()`). Wrapper `list_all.php` files that only `require index.php` are skipped (no standalone `<head>`). Other auxiliary paths (`delete.php`, export/import, partials, `join.php`, etc.) print `[SKIP]` — not `[FAIL]`. Browser output uses `colorText()` for `[PASS]` / `[FAIL]` / `[SKIP]` / `[INFO]` and `itm_script_format_modules_file_link()` for `modules/…` paths (see **Link creation rules**). Each `[FAIL]` includes a local **Open in new tab →** link (`http://localhost/it-management/modules/…`) via `itm_script_format_modules_file_local_dev_link()`. Exit `1` on in-scope title or favicon failure only.

**Favicon root-cause verify:** `php scripts/verify_favicon_root_cause.php` — Admin diagnostic for the three-layer favicon pipeline: `ui_configuration.favicon_path` + on-disk `images/favicons/company_{id}.ico` (Settings data), `config.php` `$favicon_url`, and module `<head>` favicon gate pass/fail counts (`apply_head_favicon_link.php` fixes wiring only). Optional `--module=employees` / `?module=employees` sample. Exit `1` when any seed-admin row cannot resolve a favicon URL, or when module wiring failures remain after the data layer passes.

**List new button style:** `php scripts/apply_list_new_button_style.php` — normalizes list-header `create.php` ➕ anchors to `class="btn btn-primary itm-list-new-button" title="Create"` (pairs with `styles.css` 40×40 footprint and `fields_missing` New button style gate).

**New button position helper:** `php scripts/apply_new_button_position_helper.php` — replaces inline `$ui_config['new_button_position'] ?? 'left_right'` blocks with `itm_resolve_new_button_position($ui_config)` from `includes/ui_config.php` (canonical default **`left`**). Default dry-run; `--apply` or `?apply=1` writes.

---


#### 6 . File Upload Modules

This document lists modules within the IT Management system that support file uploads, along with descriptions of their functionality, storage locations, and Apache hardening rules.

## Overview

Most modules that support file uploads have been upgraded to include a drag-and-drop area (`.itm-photo-upload-target`) for improved user experience, consistent with the `modules/tickets/` module.

Upload and tenant file trees are hardened by `itm_ensure_upload_directory()` and `itm_ensure_upload_directory_chain()` in `includes/bootstrap_helpers.php`. **Do not** call bare `mkdir()` for application upload paths.

## Force-create contract (mandatory)

Every `itm_ensure_upload_directory()` call — including each segment walked by `itm_ensure_upload_directory_chain()` — **must force-create** two managed files on that folder:

| File | Behaviour |
|------|-----------|
| **`.htaccess`** | Always **overwritten** with the canonical policy body for that directory (`upload`, `deny_http`, or `deny_all`). Never skip when a file already exists or contains an ITM marker. |
| **`index.html`** | Always **overwritten** with an empty placeholder from `itm_upload_directory_empty_index_html()`. Applies to **all** policies (including `backups/`). |

Success requires all three to exist: the directory, `.htaccess`, and `index.html`.

Empty `index.html` content (managed — do not edit by hand):

```html
<!DOCTYPE html><html><head><title></title></head><body></body></html>
```

**Every folder** in the project (every directory under the repository root, not only upload trees) **must** have an empty `index.html`. Upload paths also receive managed `.htaccess` via `itm_ensure_upload_directory()`. Missing placeholders are a directory-listing risk; deleted placeholders must be restored on the next ensure or backfill run.

**Do not** create upload folders with bare `mkdir()` and add `.htaccess` / `index.html` manually in a follow-up step — call the helper once so both files are written atomically for that path.

## Upload hardening policies

Canonical **source of truth in code:** `includes/bootstrap_helpers.php` → `itm_upload_directory_policy_body($policy)`. Helpers **always overwrite** existing `.htaccess` on ensure — never skip when a file exists (prevents uploaded `.htaccess` RCE).

| Policy | Marker (first comment) | Directories | `.htaccess` role | `index.html` | HTTP access |
|--------|------------------------|-------------|------------------|--------------|-------------|
| `upload` | `ITM upload hardening` | `images/`, `tickets_photos/`, `floor_plans/` | Disable PHP/script execution; allow static assets | Empty placeholder | Static files served directly by Apache |
| `deny_http` | `ITM files hardening` | `files/` and every segment under `files/{company_id}/…` | `RewriteRule ^ - [F]` on **each** folder in the chain | Empty placeholder | **Denied** — serve through `modules/explorer/file.php` |
| `deny_all` | `ITM backup hardening` | `backups/` | `Require all denied` | Empty placeholder | Fully blocked |

### Canonical `.htaccess` bodies (managed — do not edit by hand)

**`deny_http`** (`files/` tree — Explorer, private contacts, notes attachments):

```apache
# ITM files hardening — do not remove (managed by itm_ensure_upload_directory)
RewriteEngine On
RewriteRule ^ - [F]
Options -Indexes -ExecCGI
```

**`upload`** (`images/`, `tickets_photos/`, `floor_plans/`):

```apache
# ITM upload hardening — do not remove (managed by itm_ensure_upload_directory)
Options -Indexes -ExecCGI -MultiViews
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_authz_core.c>
    <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|asp|aspx|jsp|sh|exe|bat|cmd)$">
        Require all denied
    </FilesMatch>
</IfModule>
<IfModule !mod_authz_core.c>
    <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|asp|aspx|jsp|sh|exe|bat|cmd)$">
        Order allow,deny
        Deny from all
    </FilesMatch>
</IfModule>
RemoveHandler .php .phtml .phar .cgi .pl .py
RemoveType .php .phtml .phar .cgi .pl .py
```

**`deny_all`** (`backups/`):

```apache
# ITM backup hardening — do not remove (managed by itm_ensure_upload_directory)
Options -Indexes -ExecCGI
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
```

Empty `index.html` on every ensured folder (all policies):

```html
<!DOCTYPE html><html><head><title></title></head><body></body></html>
```

### `/files/` chain example

For `files/{company_id}/Private/{username}_{employee_id}/private_contacts/`, the system **force-creates** managed `.htaccess` and empty `index.html` on:

- `files/`
- `files/{company_id}/`
- `files/{company_id}/Common/` (when created)
- `files/{company_id}/Private/`
- `files/{company_id}/Departments/` (when created)
- `files/{company_id}/Trash/` (when created)
- `files/{company_id}/Private/{username}_{employee_id}/`
- `files/{company_id}/Private/{username}_{employee_id}/private_contacts/`

For **employee profile photos** (`files/{company_id}/Private/{username}_{employee_id}/profile/`), the same chain applies through `Private/{username}_{employee_id}/`, then:

- `files/{company_id}/Private/{username}_{employee_id}/profile/`

Legacy installs may still have `Private/{username}_{linked_user_id}/profile/`; `emp_profile_photo_serve_path()` falls back to that path when a legacy linked id is present on the employee row.

`modules/explorer/file.php` allows any authenticated company user to read `Private/*/profile/` assets (employee profile thumbnails). Other `Private/` content remains owner-scoped.

Explorer sidebar **Profile Storage** opens this folder for the logged-in user. The **Birthdays** module (`modules/birthdays/index.php`) is read-only — no uploads and no list thumbnails. See **§13 Birthdays**.

**Runtime tenant trees** under `files/{company_id}/**` must **not** be committed to git — helpers create and harden them on deploy.

### Helpers (mandatory for new code)

| Helper | When to use |
|--------|-------------|
| `itm_ensure_upload_directory($path, $policy)` | Single directory — force-writes `.htaccess` + empty `index.html` |
| `itm_ensure_upload_directory_chain($path, $policy, $anchorRoot)` | Walk anchor→leaf; force-writes `.htaccess` + empty `index.html` on **every** segment |
| `itm_ensure_files_storage_directory($absolutePath)` | Any path under `files/` — `deny_http` chain from `files/` root |
| `itm_files_serve_url($relativePath)` | Build `../../modules/explorer/file.php?path=…` for UI `<img>` / download links |
| `itm_upload_directory_empty_index_html()` | Canonical empty `index.html` body (used internally; do not duplicate) |

### Is `RewriteRule ^ - [F]` the best approach?

**For `files/` — yes, as the primary control**, combined with:

1. **PHP proxy serving** (`modules/explorer/file.php`) so authorised users still see images/files after direct HTTP is blocked.
2. **Per-segment `.htaccess`** so a malicious upload cannot relax rules in a child folder when parent rules are missing.
3. **Force-overwriting** managed `.htaccess` and empty `index.html` on every ensure (never “skip if exists”) so uploaded `.htaccess` files cannot append RCE directives and deleted `index.html` files are restored.
4. **Upload filters** (blocked extensions and dotfiles) in `modules/explorer/api.php`.

**For public asset dirs** (`images/`, `tickets_photos/`, `floor_plans/`) use the `upload` policy instead — those URLs must remain directly servable. `RewriteRule ^ - [F]` alone is insufficient there; the existing `upload` policy disables script execution while allowing images/PDFs.

**Defence in depth:** keep uploads outside the web root where possible, validate MIME/types server-side, and never rely on `.htaccess` when the app may run on nginx or without `AllowOverride`.

## Modules

### 1. Tickets
- **Path:** `modules/tickets/create.php`
- **Storage:** `tickets_photos/` (`upload` policy via `config/config.php`)
- **Description:** Allows uploading multiple photos for ticket records.
- **Implementation:** Uses `itm-photo-upload-target` with drag-and-drop support (via `js/itm-upload-helper.js`).

### 2. Calendar
- **Path:** `modules/calendar/index.php`
- **Description:** Supports importing events from an ICS file.
- **Implementation:** Upgraded to include a drag-and-drop area for `.ics` files (via `js/itm-upload-helper.js`). Works independently of theme initialization.

### 3. Employees
- **Paths:** `modules/employees/index.php` (import); `modules/employees/create.php`, `modules/employees/edit.php` (profile photo); `modules/employees/includes/profile_fields.php` (photo UI); `modules/employees/includes/profile_birthday_fields.php` (`birthday`, `hide_year`)
- **Storage (import):** Client-side only — Excel (.xlsx, .xls) or CSV parsed in the browser; no server upload path for import files.
- **Storage (profile photo):** `files/{company_id}/Private/{username}_{employee_id}/profile/` (`deny_http` chain) — see **§11 Employee profile photos** below.
- **Description:** Index supports bulk employee import via drag-and-drop. Create/edit support profile photo (PNG/JPG), `birthday`, and `hide_year`. Photo upload requires employee `username` and row `id`; filenames are `{username}_{employee_id}.png` or `.jpg`.
- **Implementation:** Import uses `.itm-photo-upload-target` via `js/itm-upload-helper.js`. Profile photo uses `.itm-employee-photo-target` and `js/itm-upload-helper.js`; upload and serve logic live in `includes/employee_profile_photo.php`.

### 4. Equipment
- **Path:** `modules/equipment/create.php` (and `edit.php` via inclusion)
- **Storage:** `images/` (`upload` policy)
- **Description:** Allows uploading one or more photos during equipment creation or editing.
- **Implementation:** Upgraded to include a drag-and-drop area with photo preview integration and auto-upload on selection during edit (via `js/itm-upload-helper.js`).

### 5. Events
- **Path:** `modules/events/index.php`
- **Description:** Provides functionality to import events from an ICS file.
- **Implementation:** Upgraded to include a drag-and-drop area for `.ics` files (via `js/itm-upload-helper.js`). Logic fixed to avoid redundant listener attachments.

### 6. Patches & Updates
- **Paths:** `modules/patches_updates/create.php`, `modules/patches_updates/edit.php`, `modules/patches_updates/index.php`, `modules/patches_updates/list_all.php`, `modules/patches_updates/view.php`
- **Storage:** `tickets_photos/` (`upload` policy)
- **Description:** Includes photo upload functionality for patch records across various views.
- **Implementation:** All relevant views upgraded to use `itmUploadHelper.setupByClass(".itm-photo-upload-target")` from `js/itm-upload-helper.js`.

### 7. Settings
- **Path:** `modules/settings/index.php`
- **Storage:** `images/favicons/` (`upload` policy per upload)
- **Description:** Allows uploading a favicon image (.ico) and importing database state from a SQL file.
- **Implementation:** Both favicon and SQL import fields upgraded with drag-and-drop areas (via `js/itm-upload-helper.js`). Restored sidebar visibility toggle logic.

### 8. Floor Plans
- **Path:** `modules/floor_plans/create_upload_view.php`, `modules/floor_plans/gallery_helpers.php`
- **Storage:** `floor_plans/{company_id}/` (`upload` policy via `fp_company_upload_dir()`)
- **Description:** Allows uploading Floor Plans (Gallery/AutoCAD/PDF).
- **Implementation:** Upgraded to include a drag-and-drop area (`.itm-photo-upload-target`) for file uploads (via `js/itm-upload-helper.js`).

### 9. Explorer
- **Paths:** `modules/explorer/api.php`, `modules/explorer/setup.php`, `modules/explorer/file.php`, `modules/explorer/index.php`
- **Storage:** `files/{company_id}/` tree (`deny_http` on every segment, including `Trash/`)
- **Description:** General file management with multi-tenant ACL (`get_full_path`), soft-delete to `Trash/`, and PHP-proxied downloads.
- **Security:** API blocks `Private` and `Departments` roots; UI uses `resolveScopedFolderPath()` for scoped navigation; trash operations are ACL-filtered; `downloadZip` blocks Home/`Common`/`Private`/`Departments`/`Trash` roots. Home shows virtual Trash only when the user has recoverable items; `listRecycle` uses leaf filter. See `modules/explorer/AGENT_NOTES.md` and **`AGENTS.md` → Explorer module**.
- **Implementation:** Standard `.itm-photo-upload-target` UI; desktop drag-and-drop upload. All folder creation uses `itm_ensure_files_storage_directory()` / `explorer_ensure_dir()`. Block dotfile uploads; managed `.htaccess` overwrites malicious uploads on ensure.
- **Regression scripts:** `php scripts/test_explorer_paths.php`, `php scripts/verify_explorer_zip_leak.php` (three-step ZIP contract); `.htaccess` RCE PoC: `verify_explorer_rce_htaccess.php`, `verify_explorer_rce_marker.php`; Import data loss: `repro_employee_dataloss.php`, `repro_generic_dataloss.php`.

### 10. Private Contacts
- **Paths:** `modules/private_contacts/index.php`, `create.php`, `edit.php`, `view.php`, `join.php`
- **Storage:** `files/{company_id}/Private/{username}_{employee_id}/private_contacts/` (`deny_http` chain)
- **Description:** Per-user vault-encrypted contacts (PNG photos, favourites, temporary QR/share via `private_contact_share_sessions`).
- **Implementation:** PII encrypted with `$_SESSION['vault_key']`; list search/sort/pagination runs on hydrated plaintext in PHP. Photos via `itm_ensure_files_storage_directory()` and `itm_files_serve_url()` → `modules/explorer/file.php`. Regression: `php scripts/verify_private_contacts_vault.php`, `php scripts/verify_qr_share_modules.php`.

### 11. Employee profile photos
- **Paths:** `modules/employees/create.php`, `modules/employees/edit.php`, `modules/employees/includes/profile_fields.php`, `includes/employee_profile_photo.php`
- **Storage:** `files/{company_id}/Private/{username}_{employee_id}/profile/` (`deny_http` chain)
- **Description:** PNG/JPG profile photos; canonical filenames `{username}_{employee_id}.png` or `{username}_{employee_id}.jpg`. Requires employee `username` and row `id` (not a linked login account). `employees.photo` stores the basename; `birthday` and `hide_year` are separate columns (not files).
- **Implementation:** `emp_profile_photo_store_upload()` validates MIME (PNG/JPEG), ensures the folder chain with `itm_ensure_files_storage_directory()`, removes the other extension when replacing, and returns the filename for `employees.photo`. UI serves via `emp_profile_photo_url()` → `itm_files_serve_url()` → `modules/explorer/file.php` (company users may read `Private/*/profile/`). Drag-and-drop UI uses `.itm-employee-photo-target` and `js/itm-upload-helper.js`. Forms require `enctype="multipart/form-data"`.

### 12. Notes
- **Path:** `modules/notes/index.php`
- **Storage:** `files/{company_id}/Private/{username}_{employee_id}/notes/` (`deny_http` chain)
- **Description:** Image attachments on notes.
- **Implementation:** Creates storage via `itm_ensure_files_storage_directory()`; previews/downloads use `itm_files_serve_url()`.

### 13. Birthdays
- **Path:** `modules/birthdays/index.php`
- **Storage:** None — read-only monthly list; no file uploads.
- **Description:** Lists employees with a `birthday` in the selected month, filtered by **Employment Status** (multi-select; default **Active** and **On Leave**). Name column is text only (no profile thumbnails). Day column uses `emp_format_birthday_day_only()` (day of month without leading zeros). Search queries name, day, `departments.code`, and `departments.name`.
- **Implementation:** Month filter, **Employment Status** multi-select, and **Search (all fields)** on the filter card; table export controls follow standard `table-tools.js` behaviour where enabled.

## Folder creation map (code references)

| Location | Helper / policy | Force-created files per folder |
|----------|-----------------|--------------------------------|
| `config/config.php` | `upload` on `images/`, `tickets_photos/`, `floor_plans/`; `deny_all` on `backups/`; `deny_http` on `files/` | `.htaccess` + empty `index.html` |
| `modules/explorer/api.php` | `itm_ensure_files_storage_directory()` for all folder operations | `.htaccess` + empty `index.html` on each chain segment |
| `modules/explorer/setup.php` | `itm_ensure_files_storage_directory()` | `.htaccess` + empty `index.html` on each chain segment |
| `modules/private_contacts/create.php`, `edit.php` | `itm_ensure_files_storage_directory()` | `.htaccess` + empty `index.html` on each chain segment |
| `modules/employees/create.php`, `edit.php` (`includes/employee_profile_photo.php`) | `itm_ensure_files_storage_directory()` | `.htaccess` + empty `index.html` on each chain segment |
| `modules/notes/index.php` | `itm_ensure_files_storage_directory()` | `.htaccess` + empty `index.html` on each chain segment |
| `modules/floor_plans/gallery_helpers.php` | `itm_ensure_upload_directory($base, 'upload')` | `.htaccess` + empty `index.html` |
| `modules/settings/index.php` | `itm_ensure_upload_directory($faviconsDirFs, 'upload')` | `.htaccess` + empty `index.html` |
| `modules/equipment/create.php` | `itm_ensure_upload_directory(UPLOAD_PATH, 'upload')` | `.htaccess` + empty `index.html` |

## Maintenance scripts

| Script | Scope | What it force-writes |
|--------|-------|----------------------|
| `php scripts/empty_folders.php` | **Entire project** (every folder under repo root; skips `.git`, `.github`, and other dot dirs) | Empty `index.html` on **every** folder; managed `.htaccess` + `index.html` on upload paths (`images/`, `tickets_photos/`, `floor_plans/`, `backups/`, `files/`) |
| `php scripts/ensure_files_htaccess_chain.php` | `files/` only | `deny_http` `.htaccess` + empty `index.html` on every segment (idempotent) |
| `php scripts/apply_crud_record_share_modules.php` | CRUD record share rollout modules | **Browser + CLI** via `itm_apply_script_bootstrap.php`. Dry-run default; `--apply` / `?apply=1` (Admin) writes `join.php`, AJAX handler, view share buttons, and QR modal includes via `includes/itm_crud_record_share.php` |
| `php scripts/fix_crud_record_share_view_buttons.php` | Scaffold `index.php` inline view blocks | Repairs share button markup when view needle pattern drifted (dry-run default; `--apply` to write) |
| `php scripts/patch_crud_share_agent_notes.php` | CRUD record share rollout modules | **Browser + CLI**. Appends **Share (temporary QR / code)** section to each module `AGENT_NOTES.md` (dry-run default; `--apply` / `?apply=1` Admin) |

Run `empty_folders.php` after deploy, when adding new directories, or when folders were created without placeholders. The script lists only **new or changed** paths (repo-relative `index.html`) before the summary line. A second run on an unchanged tree prints `No new or changed folders.` and reports how many folders were already current.

```bash
php scripts/empty_folders.php
```

Example output (first run after adding folders):

```
Scanning project folders for missing or outdated index.html...

modules/new_module1/index.html
modules/new_module2/index.html
[PASS] Updated 2 folder(s) under /path/to/it-management (0 upload-hardened). 249 already current (251 scanned).
```

Example output (subsequent run — nothing to do):

```
Scanning project folders for missing or outdated index.html...

No new or changed folders.
[PASS] Updated 0 folder(s) under /path/to/it-management (0 upload-hardened). 251 already current (251 scanned).
```

`files/` only (faster when other roots are already correct):

```bash
php scripts/ensure_files_htaccess_chain.php
```

## Technical Standards

- **Shared Utility:** `js/itm-upload-helper.js` provides centralized drag-and-drop logic.
- **CSS Classes:**
  - `.itm-photo-upload-target`: The primary container for the drag-and-drop area.
  - `.is-dragover`: Applied to the target during drag events to provide visual feedback.
  - `.itm-dropzone-hint`: Used for instructional text within the dropzone.
- **JavaScript:** Implementation involves using `itmUploadHelper.setupById(targetId, inputId)` or `itmUploadHelper.setupByClass(className)`. The helper handles preventing default drag events, toggling visual states, and assigning files to the input while triggering the `change` event.

## Deployment & Git

| Script | Purpose |
|--------|---------|
| `deletev2.php` | Remote deployment tool: clones GitHub repository and imports the database. |
| `reset_git_history.php` | **BETA only**: Destructive utility to completely reset Git history and force-push a clean master branch. |

## Recent Maintenance Summary

### Security Reproduction & Verification
Added or updated the following scripts in the catalog to ensure comprehensive security auditing:
- `repro_bac.php` / `repro_bac_updated.php`: IDFs API access control.
- `repro_rce.php` / `repro_rce_updated.php`: Floor Designer file upload security.
- `repro_sqli.php` / `repro_sqli_updated.php`: Floor Designer SQL injection guards.
- `repro_explorer_traversal.php` / `verify_explorer_fix*`: Explorer path traversal suite.
- `repro_bug.php`: Todo module visibility and security.

### Utilities
- `benchmark_user_config.php`: Performance testing for optimized stats gathering.
- `benchmark_stats_optimized.php`: Performance benchmark for consolidated stats query.
- `repro_equip_issues.php`: Mocking framework for equipment module diagnostics.
- `idf_device_port_sort_test.php`: Regression test for IDF device port list sorting.
- `crud_tables.php`: Audits module-to-table mapping.
- `crud_titles.php`: Audits module-to-title mapping.
- `crud_actions.php`: Audits module-to-action mapping.
- `test_visualizer_v2.php`: Visual test for Equipment Port Visualizer.

### Pathing Standard
The `fixed_files/` directory is officially obsolete and has been removed from all functional scripts. All verification and reproduction scripts must target the live code in `modules/`. Path issues in reproduction scripts (`repro_rce.php`, `repro_bac.php`, `repro_sqli.php`, `repro_select_options.php`) and `generate_tests.php` have been resolved.

### Standardization
Standardized output handling using `scripts/lib/script_cli_output.php` has been applied to several functional and reproduction scripts for consistent reporting across Browser and CLI environments.

### System-Wide Path & Include Verification
Performed a rigorous audit of all file path and `require`/`include` context behaviors. Confirmed that relative file references correctly resolve using parent/child directories (via proper `chdir()` context switches where relevant). Verified complete removal of obsolete `fixed_files/` directory, cementing `modules/` as the sole authoritative code target for reproduction and verification scripts.
