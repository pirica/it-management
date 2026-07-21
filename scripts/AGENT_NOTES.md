# AGENT_NOTES.md - Scripts

## 1. Module Purpose
Contains utility scripts, database maintenance tools, security audits, and testing runners.

## 2. Key Tables
- Interacts with almost all tables for maintenance, auditing, and seeding.

## 3. Required Relationships
- Depends on the entire database schema as defined in `db/03_triggers.sql`.

## 4. Business Rules (Critical for Agents)
- **Pre-implementation discovery:** before adding or changing scripts, produce the architectural map, module summary, and dependency analysis required by **`scripts/SCRIPTS.md` → Pre-implementation discovery (scripts)** and **`AGENTS.md` step 4**.
- **CLI Mode**: Scripts intended for CLI use define `ITM_CLI_SCRIPT` under CLI SAPI to bypass session redirects. **Browser + CLI** is the default for `scripts/*`; use `scripts/lib/itm_script_regression_entry.php` when a script needs an explicit Admin browser gate. **Dry-run** repo/DB writers use `scripts/lib/itm_apply_script_bootstrap.php` (default preview; `--apply` / `?apply=1`). Static `check_*` audits use `scripts/lib/itm_script_access_helpers.php` → `itm_check_script_begin_browser_admin()`. **CLI-only** tools: bash wrappers, `bypass_login.php`, or `itm_script_prepare_cli_entry()`.
- **Test sessions vs Admin**: `scripts/lib/itm_script_bootstrap.php` (loaded from `config.php`) swaps browser `scripts/*` runs to disposable test Admin/employee sessions (`itm_script_begin_browser_isolated_session()`), restores the real cookie on shutdown, and blocks disposable sessions outside `scripts/*.php`. **`csrf_token` is copied into the isolated session on begin, synced into the pre-swap backup when forms call `itm_get_csrf_token()`, and merged back on shutdown**; `itm_validate_csrf_token()` also accepts the pre-swap backup during POST. In-process session tests must use **`itm_script_with_test_session_context()`** — not the signed-in Admin session.
- **DANGER**: Some scripts are destructive (e.g., `reset_git_history.php`, `repair_table_from_schema.php`). Use with extreme caution. **`reset_git_history.php`** rewrites Git history and force-pushes; **BETA / pre-production only** — catalogued under Deployment & Git in `scripts/scripts.php`; no implementation changes while the project remains in BETA.
- **Production Safety**: `debug.php` and other diagnostic tools should be removed or blocked in production.

## 5. UI Behavior Requirements
- **Browser vs CLI**: Many scripts provide both a plain-text/HTML browser view and a CLI output mode.
- **`scripts.php` catalog layout**: responsive card grid. Access badges: **Browser** + **CLI** (standard for `scripts/*.php` with a catalog link), or **CLI-only** (`scripts-badge-cli-only`) for bash/Python/bypass helpers. No separate dry-run badge — dry-run behaviour is documented in **How to use** (`--apply` / `?apply=1`). CSS: `scripts-badge-web`, `scripts-badge-cli`, `scripts-badge-cli-only`.
- **Table Line Breaking**: Audit/report pages such as `crud_tables.php`, `crud_titles.php`, and `crud_actions.php` keep `white-space: nowrap` on table cells with horizontal scroll — see each script’s inline CSS. Do not apply that nowrap pattern to the `scripts.php` catalog cards.
- **crud_tables.php** — reads `index.php` for `$crud_table` only (no DB). Skip slugs: `docs/list_bespoke_UI.txt` + `scripts/data/crud_tables_skip_modules.txt` via `scripts/lib/itm_crud_tables_audit.php`.
- **crud_titles.php** — reads `index.php` for `$crud_title` only. Skip: `is_*` equipment shortcuts + same bespoke list via `itm_crud_titles_should_skip_module()` in `scripts/lib/itm_crud_tables_audit.php`.
- **crud_actions.php** — scans `index.php` plus CRUD wrapper entry files for `$crud_action`. Skip when no assignment and module is non-standard CRUD (`itm_crud_actions_should_skip_module()` — `is_*` or missing `$uiColumns` + `cr_manageable_columns()` in `index.php`); standard scaffold modules with no assignment remain **N/A**. Browser nav comes from `itm_script_output_begin()` — do not call `itm_script_browser_nav_echo()` again in custom HTML (duplicate ← Scripts index). Static gate: **`check_script_browser_nav_duplicate.php`** (catalog + **`SCRIPTS.md` → Browser scripts**).

## 6. API Actions (If Applicable)
- **api.php** — browser HTML catalogue of JSON/AJAX endpoints (session + CSRF). Documents Explorer file actions, Switch Port Manager (`includes/get_ports.php`, `includes/update_port.php` — `itm_api_json_response()`, mysqlnd-safe fetch helpers), IDF `api/*`, module imports, **License Management** (`license_management`, `license_types`, Type quick-add via `select_options_api.php`), passwords vault, notes/todo AJAX, System Status API, API key rate limits (**Free** = no key, session required; paid = key required), and tier regression runners (`apitest_tier_free.php`, `apitest_tier_basic.php`). Collector helpers: `itmDocCollectDocumentedJsonHandlerPaths()`, `itmDocFileEmitsJsonResponse()` (used by `verify_api_coverage.php`); PHPUnit: `phpunit/tests/Unit/Scripts/ApiFunctionsTest.php`. Maintenance rules: **`scripts/SCRIPTS.md` → API documentation (`scripts/api.php`)**.

## 7. File Structure
- **smoke_test.sh** — main shell script for linting and security coverage (steps 1–4: syntax lint, CSRF, SQLi, FK label search static audit).
- **run_tier2_checks.php** — batch runner for all Tier 2 static `check_*` scripts from `SCRIPTS_TEST_MATRIX.md` (parse or built-in fallback); CLI flags `--continue`, `--list`, `--only=`; browser menu with `?run=1`. Shared lib: `lib/itm_tier2_check_scripts.php`. Excluded from `perform_audit.php` subprocess scan.
- **SCRIPTS_TEST_MATRIX.md** — full catalog test matrix (tiers 0–5, runner coverage, destroy→fresh-clone protocol). Destroy log template: **`data/scripts-matrix-destroy-log.md`**. Latest safe-matrix run report (A–Z Passed/Skipped/Excluded/Covered + Failures): **`data/scripts_errors.txt`**. See **`scripts/SCRIPTS.md` → Full scripts test matrix**.
- **verify_database_sql_import.sh** — full `db/` import + table-count assertion (derived from `CREATE TABLE` lines, currently 130); CI job **database-import** in `.github/workflows/smoke.yml` (also runs `verify_crud_fk_label_search.php`). Local split alternative: `import_database_split.sh` + `db/AGENT_NOTES.md`.
- **import_database_split.sh** — imports `db/01_schema.sql`, `db/02_data.sql`, `db/03_triggers.sql` in one session (order 01 → 02 → 03). See `db/AGENT_NOTES.md`.
- **check_fk_label_search_coverage.php** — static audit that every module with server-side list search matches visible FK/label columns; smoke step 4; universal pass rules only (shared FK helpers, scalar column helper, EXISTS/JOIN label LIKE, employee JOIN/CONCAT, or scalar-only fields) — no per-module N/A allowlist. **Browser + CLI** (Administrator session in browser via `itm_script_require_admin_script_or_exit()`).
- **verify_crud_fk_label_search.php** — MySQL regression for FK label list search (employees, license_management, switch_ports, todo, notes, private_contacts, ip_subnets, bookmarks, passwords); CI **database-import** job after schema import.
- **verify_employees_equipment_search_coverage.php** — MySQL regression for employees + equipment index search: scalar identity (`first_name`, `last_name`, `username`, full name, `mobile_phone`), FK labels (`Active`, `Helpdesk`, `Limited`, `Team member`, `Laptop Only`, `Individual`, `IT Manager`, `FNB`, `LOC-NY-01`, position description, manager username), equipment scalars (serial, hostname, model, notes, purchase cost) and FK labels (`Switch`, `Cisco`, status, codes, assignee identity); disposable rows via `itm_script_test_employee.php`; optional `ITM_TEST_COMPANY_ID`.
- **apply_crud_audit_soft_delete.php** / **check_crud_audit_soft_delete.php** — scaffold soft-delete + audit meta UI rollout (`docs/list_soft-delete.txt`). Apply is **Browser + CLI**; default run is always dry-run; writes only with `--apply` (CLI) or `?apply=1` (browser, Admin); after the count summary prints inventory / skipped / missing / needing-patch / compliant module lists with real newlines (not browser `<br><br>`); skips status-driven modules; idempotent when already compliant. See **`scripts/SCRIPTS.md` → Scaffold audit columns + soft-delete**.
- **All `scripts/apply*.php` maintenance tools** — shared contract via **`lib/itm_apply_script_bootstrap.php`**: Browser + CLI, dry-run default, `--apply` / `?apply=1` (Admin) to write, named target lists after the count summary. Catalog: `scripts/scripts.php`. **`apply_module_sample_data_seed.php`** — propagates sample `INSERT`s into `db/` for every seeded `company_id` (single-row and multi-row `VALUES` blocks); **mirror mode** detects `INSERT … SELECT N, … FROM table WHERE company_id = 1` and appends only to the source tenant VALUES (e.g. `knowledge_base`); browser errors via `itm_seed_fwrite_stderr()`; built-in help lists dry-run/apply URLs.
- **verify_audit_columns.php** — schema gate for mandatory audit columns including soft-delete fields.
- **verify_audit_logs_disclosure.php** — three-step employees audit disclosure regression (static `db/` trigger scan, live disposable UPDATE probe, retro scan); prints each step with `[PASS]`/`[FAIL]`.
- **run_tests.php** — central test runner; browser menu (standard vs HTML coverage); detects Xdebug/PCOV; post-run link to `phpunit/coverage/html/coverage.html`. Browser coverage URL: `run_tests.php?run=1&mode=coverage`. Full docs: **`scripts/SCRIPTS.md` → PHPUnit test runner**.
- **check_script_browser_nav_duplicate.php** — static audit that `scripts/*.php` (excluding `lib/`) does not stack two **← Scripts index** links in one browser response (`itm_script_output_begin()` already calls `itm_script_browser_nav_echo()`). Browser + CLI plain-text report; run after script browser-shell changes; exit `1` lists offending files.
- **check_index_table_compliance.php** — index list contract (`data-itm-db-import-endpoint`, Actions `data-itm-actions-origin` / `itm-actions-cell`, POST CSRF). Honors `data-itm-no-import-excel="1"` (no import required) and skips Actions markers when the index has no Actions column. Actions `th`/`td` detection is attribute-order independent. Browser report HTML-escapes lines inside `<pre>`. Baseline: `data/index_table_compliance_baseline.txt`.
- **check_pagination_emoji.php** — static audit for list pagination emoji-only visible labels (`⏮️`/`◀️`/`▶️`/`⏭️`) and word-only `title` attributes; scans module list sources (index with `itm_ui_merge_thin_router_audit_content()`, `list_all.php`/`view.php`/`delete.php`, `includes/partials/render.php`, `tabs/*.php`); uses `itm_check_pagination_nav_titles()` in `lib/itm_ui_list_contract_checks.php`. Browser + CLI; exit `1` on violation.
- **check_database_sql_company_name_uniques.php** — tenant UNIQUE audit over `db/` (via `includes/database_sql_unique_audit.php`). Skips intentional duplicate-name / junction tables including `bookmark_folders` and `floor_plan_item_tags`, private `events`, and ephemeral `*_share_sessions` tables (see `includes/itm_qr_share.php`, `modules/events/AGENT_NOTES.md`).
- **verify_select_options_escalation.php** — regression for Select Options API table whitelist (`includes/itm_select_options_policy.php`); see **`scripts/SCRIPTS.md` → Select Options API verification**.
- **verify_notes_ajax_contract.php** — Notes AJAX blocked mutations return HTTP 404 with `ok:false` when `affected_rows === 0`; subprocess uses CLI `php.exe`, session before `config.php`, and asserts the owner's note was not soft-deleted (`active=1`, `deleted_at IS NULL`).
- **verify_metadata_column_cache.php** — table-level `information_schema` cache in `itm_table_has_column()` / `itm_table_column_is_nullable()`; cold schema Questions delta 1–2, warm repeat schema delta 0 (measurement excludes trailing `SHOW STATUS`).
- **verify_json_import_validation.php** — JSON import rejects invalid numeric/date column values instead of silent NULL inserts.
- **repro_employee_dataloss.php** / **repro_generic_dataloss.php** — CLI regressions for import UPDATE column preservation (`providedFields` / non-destructive UPDATE); transactional seed/cleanup; exit non-zero on failure. Catalog: CLI-only rows in `scripts/scripts.php` (admin browser gate on catalog page).
- **verify_maintenance_scripts_rbac.php** — browser Admin gate on MBQA runner, compare_database_sql_modules, and test_sql_injection.
- **lib/script_cli_output.php** — browser `<pre>` wrapper, `colorText()`, `itm_script_shell_stderr_discard()` for cross-platform subprocess stderr suppression.
- **lib/itm_script_test_employee.php** — disposable `employees` rows for repro/verify scripts and PHPUnit (`script-{slug}-{hex}` usernames, snapshot/restore, teardown delete). Static guards: `check_script_disposable_employees.php` (hardcoded id `1` / stale `$_SESSION['user_id']`), `check_stale_user_id_sql.php` (legacy `user_id` SQL / `users` table in app code), and `check_stale_user_terminology.php` (`Users module` prose, `employee_companies.user_id` helpers, session `role_name` admin checks in modules, `cr_username_for_user_id`, `'user_id'` in CRUD `$hidden` arrays). Maintenance: `apply_crud_hidden_employee_id_alias.php`.
- **apitest_tier_free.php** / **apitest_tier_basic.php** — disposable `ui_configuration` tier rate-limit regressions; entry via **`scripts/lib/itm_script_regression_entry.php`** (browser + CLI; Admin in browser); slot employees (`apitest-user-{id}`) seed via **`scripts/lib/itm_api_tier_test_helpers.php`**; in-process resolve uses **`itm_script_with_test_session_context()`**; Free HTTP probe uses **`itm_script_publish_isolated_http_session()`** (before any output in `apitest_tier_free.php`).
- **verify_company_module_access.php** — registry/CMA regression plus sidebar discovery probes (registry-only, new MySQL table, folder-only, both, neither); PHPUnit wrapper: `phpunit/tests/Unit/Scripts/CompanyModuleAccessVerifyTest.php`.
- **verify_dashboard_active_employees.php** — static + live checks for dashboard **Active** / **On Leave**: helper call-sites, rejects leftover `LOWER(es.name)` / join-predicate SQL, asserts `itm_employee_count_by_employment_status_name()` matches `company_id` + `employment_status_id` + `deleted_at IS NULL`; optional `ITM_TEST_COMPANY_ID`.
- **verify_dashboard_online_employees.php** — dashboard **Online now** / session presence regression.
- **benchmark_sidebar_module_access.php** — read-only MySQL `Questions` benchmark for sidebar structure + `has_module_access()` filter vs uncached legacy N+1 simulation; shared lib `lib/itm_benchmark_sidebar_access.php`; env thresholds `ITM_BSMA_MAX_FULL_QUERIES`, `ITM_BSMA_MIN_REDUCTION_PCT`. See **`scripts/SCRIPTS.md` → Sidebar module-access benchmark**.
- **verify_ops_report.php** — D-2 edit lock, `ops_report` CRUD, cascade delete, audit triggers on all `ops_report*` tables, registry row; browser or CLI via `lib/script_cli_output.php`; PHPUnit: `OpsReportTest`, `OpsReportPermissionsTest`.
- **verify_ops_report_sample_data.php** — empty-tenant **Add sample data** for all seven `ops_report_*` child modules (`ops_report_id` parent seed); CLI only; mutates company `4` test rows.
- **verify_rack_planner.php** — price source sync (`catalog:` / `equipment:` / `idf_unlinked:`), handlers wiring, audit triggers. Catalog: **`scripts/scripts.php`**; PHPUnit: `RackPlannerTest`.
- **verify_request_password.php** — RBAC, HMAC approval links, list contract markers, creator-only soft-delete. Catalog: **`scripts/scripts.php`**; PoC: `repro_request_password_bypass.php`.
- **verify_chatbot.php** — `chat_api.php` guards, `js/chatbot.js` XSS contract, `enable_chatbot` gating, tenant-scoped KB search. Catalog: **`scripts/scripts.php`**.
- **verify_reports_hub.php** — Reports Hub regression: all `modules/reports/api/helpers.php` payloads, Hotel Operations MTD (`ops_report`, `ops_report_fb_outlet`), budget vs actual / YoY totals, `modules_registry` slug `reports`, core chart canvas ids; optional `ITM_TEST_COMPANY_ID`; browser + CLI via `lib/script_cli_output.php`.
- **verify_system_status.php** — `modules/system_status/` layout, `modules_registry` row, native API payloads, storage tree + active DB table reports, `information_schema`; Windows also checks `shell_exec`, `is_readable()` on each `includes/*.ps1`, and `test_*.php` wrappers. Related: `system_status_api.php`, `system_status_phpinfo.php`, `take_screenshots_modules.py` (README `docs/readme/system_status.png`; cookie domain from base URL hostname). PHPUnit: `SystemStatusApiTest`.
- **repro_audit_token_leak.php** / **repro_rbac_bypass.php** / **repro_vulnerabilities.php** / **repro_esa_vulnerability.php** / **repro_auth_bypass_v3.php** / **repro_employee_companies_leak.php** / **repro_employee_companies_bac.php** / **repro_contacts_idor.php** / **repro_select_options.php** / **repro_status_leak.php** / **repro_visitors_bac.php** / **repro_visitors_sqli.php** / **repro_birthdays_resignations_rbac.php** — security repro scripts; subprocess spawns use `escapeshellarg()` and inherit `mysqli.default_socket` when set; **repro_rbac_bypass.php** seeds via a free `cost_centers` slot, uses Laragon CLI `php.exe` (not `php-cgi`), restores session before `config.php`, and falls back to permission-helper + row retention when subprocess auth redirects to login; audit token repro uses prepared statements for disposable test-user `reset_token` updates. **repro_contacts_idor.php** uses shared disposable employees and clears `@app_employee_id` before create (stale/0 audit actors caused `audit_logs_ibfk_employee` FK failures).
- **repro_destructive_import.php** — employees import destructive-delete repro for company 1; browser + CLI dry-run default; <code>--apply</code> / <code>?apply=1</code> (Admin) seeds disposable Keep/Delete Me employees via <code>itm_script_test_employee_create()</code>, imports only Keep Me through <code>modules/employees/index.php</code>, asserts Delete Me row survives (<code>deleted_at IS NULL</code>), tears down via <code>itm_script_test_employee_delete()</code>.
- **repro_select_options_unauthorized_v2.php** — regression that <code>companies</code> is blocked from Select Options quick-add; embedded scenario matrix before live API subprocess; browser subprocess prefers Laragon CLI <code>php.exe</code> (skips <code>php-cgi</code> from Apache <code>PHP_BINARY</code>) and sets <code>SCRIPT_NAME</code>/<code>DOCUMENT_ROOT</code> for correct harness auth; policy fallback remains when subprocess is still unusable.
- **verify_explorer_zip_leak.php** / **verify_explorer_rce_htaccess.php** / **verify_explorer_rce_marker.php** / **verify_user_idor.php** / **verify_company_deletion.php** / **verify_select_options_escalation.php** / **verify_employees_sensitive_view.php** / **verify_audit_updated.php** / **verify_clear_table_fix.php** / **verify_explorer_updated.php** / **verify_rbac_updated.php** / **verify_sqli_updated.php** / **verify_status_leak_fixed.php** / **verify_visitors_bac_fix.php** / **verify_visitors_sqli_fix.php** — isolated subprocess verify scripts; session simulation uses `employee_id`; `escapeshellarg()` on PHP binary and temp file. **verify_explorer_zip_leak.php** blocks Home/Common/Private/Departments/Trash roots and requires scoped Private backup ZIP; subprocess uses session before `config.php` and Laragon CLI `php.exe`. **verify_visitors_bac_fix.php** now points to the live module at `modules/visitors_access_log/index.php`.
- **verify_employee_type_resignations.php** — `employee_type` seed, `employees.start_date` / `employee_type_id`, registry slugs, weekly resignations SQL filter (`itm_iso_week_bounds()`, `MONTH(termination_date)`, `itm_sql_valid_date_predicate()`); browser or CLI via `lib/script_cli_output.php` (do not use `fwrite(STDERR)` on web SAPI).
- **verify_auto_scaffolding.php** — verification script for dynamic auto-scaffolding toggle `enable_auto_scaffolding`.
- **list_boolean_integer_fields.php** — parses db/ and live database to list Boolean/tinyint/int/integer columns, matched to modules by name.
- **list_enum_fields.php** — parses db/ and live database to list ENUM columns, matched to modules by name.
- **extract_by_fields.php** — parses db/ to extract column definitions matching keywords (by, to, employee_id, employee), matching tables to modules by name.
- **debug_resignations_termination_date.php** — read-only diagnostic for `modules/resignations/index.php` weekly filter. Default probe date `18/06/2026` (ISO week 25), `company_id=1`. Optional `employee_id` (0 = disposable probe only). Tests literal-date predicates (not dependent on existing rows), simulates module SQL with a disposable `MBQA-RESIGN-DEBUG-*` employee (same contract as `verify_employee_type_resignations.php`), then deletes the probe.
- **debug_equipment_create_rollback_errno.php** — read-only diagnostic for `modules/equipment/create.php` transaction saves. Deliberate failing `equipment` INSERT (`status_id` NULL) compares `mysqli_errno()` / `mysqli_error()` and `itm_format_db_constraint_error()` before vs after `mysqli_rollback()`. Exit `1` when rollback clears the error (explains generic “Review the required fields” UI text). CLI `--company_id=1`; browser `?company_id=1`.
- **employee_fields_missing.php** — employees-only wrapper around **`fields_missing.php`** / `itm_fields_missing_report.php`. Catalog: **`scripts/scripts.php`**.
- **fields_missing.php** — all-module schema/UI audit; optional **`--strict-gate`**. Catalog: **`scripts/scripts.php`**; reviewed JSON contract: **`SCRIPTS.md`** → *fields_missing reviewed exceptions*.
- **fields_missing_reviewed.php** — manifest for `scripts/data/fields_missing_reviewed.json`. Catalog: **`scripts/scripts.php`**.
- **ui_configuration_reviewed.php** — manifest for `scripts/data/ui_configuration_reviewed.json` (`[n/a][*][reviewed]` per-line output and matching `[reviewed]` footer bullets in `check_ui_configuration_coverage.php`). Catalog: **`scripts/scripts.php`**.
- **verify_emails_module.php** — Email Management tables, registry row, SMTP/alert seeds, `itm_send_email()` helper, static `user-config.php` vault-key notification contract (subjects + transactional template, no master-key variables in mail args), delivery test scripts, and company 1 alert-runner 30-day window (hard fail when empty; inserts disposable license sample then cleans up).
- **TOTP / vault unlock helpers** — `includes/itm_totp_helpers.php` (`PHPGangsta_GoogleAuthenticator`, encrypted `employees.totp_secret`), `includes/itm_vault_unlock.php` (shared lock screen + unlock POST). PHPUnit: `php scripts/run_tests.php --filter TotpTest`. Schema: `db/migrations/employee_totp.sql` + `db/01_schema.sql` (`totp_secret`, `totp_enabled`). Canonical doc: `docs/VAULT.md`.
- **verify_bookmarks_import.php** — Bookmarks HTML import (`L1/L2` nested folders), duplicate URL skips without orphan folders, legacy `name_hash` folder match, CSV folder target; disposable script employee + `data/bookmarks_import_sample.html`.
- **verify_bookmarks_folder_move.php** — Bookmarks folder reparent vs merge into same-named sibling (`bkm_move_folder()`, `bkm_merge_folder_into()`).
- **verify_user_config_profile.php** — `user-config.php` profile field regression: home-company UPDATE vs tenant switcher, birthday/theme/emergency round-trip, profile photo URL must be app-absolute Explorer proxy (not `../../modules/…`).
- **floor_plans_folder_move_test.php** — regression for floor-plan folder create/move and company upload hardening (`.htaccess` + `index.html` via `fp_company_upload_dir()`).
- **data/** — static allowlists/baselines for audits (`ui_configuration_excluded_modules.txt`, `fields_missing_reviewed.json`, `ui_configuration_reviewed.json`, `multi_tenant_leak_allowlist.json`, …).
- **bypass_login.php** — CLI-only utility to authenticate as **Admin** without the UI. Resolves the target user via prepared statement + `itm_mysqli_stmt_fetch_assoc()` (mysqlnd fallback), rejects non-admin users via `itm_is_admin()`, then sets session keys (including `vault_key` for Passwords). Not for production use.
- **test_ajax.php** / **test_edit.php** — CLI-only Notes session-mock harnesses; require positional `<PHPSESSID>` + title (+ note id for edit); exit `1` with usage when argv is missing; excluded from `perform_audit.php` and CSRF coverage (`check_csrf_coverage.php`).
- **take_screenshots_modules.py** — Python script using Playwright to automate screenshot capture for README.
- **check_phones.php** / **list_phone_columns.php** — PII auditing for phone-related columns in `db/03_triggers.sql`. Browser or CLI via `lib/script_cli_output.php`.
- **list_active_and_checkboxes.php** — Audits `active` field UI for modules whose resolved `$crud_table` has an `active` column (`information_schema`). Flags forbidden text inputs, non-compliant scaffold checkboxes (`itm-checkbox-control`), and status-driven modules (`employees`, `equipment`, `patches_updates`, `tickets`) with visible row `active` on forms. Status-driven scaffold passes when `itm_crud_render_form_hidden_active_input()` is present and `$formColumns` omits `active` (business status is `status_id` / `employment_status_id` on the `*_status` lookup — e.g. `patches_updates` → `patches_updates_status`). Shared builder: `scripts/lib/itm_list_active_and_checkboxes_report.php`. CLI exits `1` on violations; `--all` lists compliant files.
- **fix_scaffold_active_checkbox.php** — Repairs `scaffold_active_checkbox_not_compliant` violations via `scripts/lib/itm_active_checkbox_fix.php`. Browser module select + dry-run/apply; CLI `--module=` / `--all`. Catalog: **`scripts/scripts.php`**.
- **identify_modules.php** — Scans modules; browser JSON preview (Admin, optional `?save=1` for `modules_metadata.json`); CLI redirect to `modules_metadata.json`.
- **generate_tests.php** — Generates PHPUnit tests from `modules_metadata.json`; skips modules whose `*Test.php` already exists; browser dry-run (Admin), `?apply=1` writes missing only; CLI writes missing immediately.
- **count_args.php** — Audits `trg_employees_audit_insert` trigger arguments. Browser or CLI via `lib/script_cli_output.php`.
- **check_delimiters.php** / **check_duplicates.php** / **verify_sql.php** / **check_sql_errors.php** — SQL audit tools for `db/`. Browser or CLI via `lib/script_cli_output.php`.
- **fix_sql.php** / **fix_sql_broad.php** / **fix_sql_departments.php** — Maintenance for `db/` (active columns, triggers, column counts). CLI only.
- **schema_report.php** / **validate_DB_schema.php** / **test_employee_id-foreign_keys.php** / **validate_delete_employee.php** — Database schema validation suite. **`itm_schema_collect_validation_issues()`** returns `errors`, `warnings`, and `skips` (intentional `ON DELETE CASCADE` on `employee_id` → `employees` is classified as **SKIP DELETE CASCADE**, not a warning). **`schema_report.php`** — Admin browser HTML report with Errors / Warnings / Skipped sections; uses scoped `.schema-report-wrap` (not global `.container` from `styles.css`). **`validate_DB_schema.php`** — CLI/browser text output with `[ERROR]`, `[WARN]`, and `[SKIP]` prefixes; exit code `1` only when errors exist.
- **generate_FK_employee_id.php** / **delete_clone_employee.php** — Employee data maintenance tools.
- **generate_reassignment.php** — Admin browser + CLI reassignment planner (`scripts/lib/itm_employee_reassignment.php`). Filter by **employee id** (`employee_id` / `--employee-id` / `from_id`); optional target for apply SQL. Default **rows_only** hides 0-row tables; `all_tables=1` shows full inventory. Bare CLI (no id) prints usage and **exit 0**; invalid plan/apply still exit 1.
- **transfer_data_from_employee.php** — Admin browser form + CLI. Default **dry-run** (single transaction rolled back). `--apply` / `?apply=1` creates a clone and copies all tenant tables with `employee_id` (probe per table on apply; full transaction rollback on dry-run). Rewrites `knowledge_base.title` and `rack_planner.name` with a `-clone-{id}` suffix so company-scoped UNIQUE keys do not block the copy.
- **benchmark_stats_optimized.php** — Performance benchmark for consolidated stats query.
- **benchmark_user_config.php** — Legacy 4 alerts/events COUNT loop vs production `itm_user_config_fetch_stats_batch()` + `itm_user_config_extract_alerts_events_counts()`; `[PASS]` requires matching counts and extract faster than legacy loop.
- **repro_explorer_traversal.php** — Repro script for Explorer Path Traversal vulnerability via 'item' parameter.
- **verify_source_utf8_mojibake.php** — UTF-8 / mojibake static audit via `scripts/lib/itm_mojibake_audit.php`. Browser + CLI; optional `--path=` / `?path=` scope. Detect-only (no writes). Catalog: **`scripts/scripts.php`**.
- **fix_source_utf8_mojibake.php** — Repair known mojibake literals (shared lib). Browser selection mode: Select to Fix → check files → Preview Selected / Fix Selected (Admin apply). CLI `--files=` / `--apply`. Bulk all-files: **`apply_utf8_mojibake_fix.php`**.
- **explorer_human_test.php** — Human-flow Explorer API regression (ACL, CRUD, explorer table soft-delete sync, audit). Browser (Admin) via `itm_script_regression_entry.php`; disposable user via `itm_script_with_test_session_context()`; browser intro + module link outside `<pre>`, pass/fail log uses `\n` inside `<pre>`. Mutates DB/filesystem via temporary company teardown. See **`scripts/SCRIPTS.md` → Explorer**.
- **verify_explorer_fix.php** / **verify_explorer_fix_updated.php** — Verification for Explorer Path Traversal fix.
- **verify_explorer_fix_web.php** / **verify_explorer_fix_standalone.php** — Web-friendly and standalone HTML UI verification for Explorer Path Traversal fix.
- **verify_import_fix_updated.php** — Verification for Employee Import Department Data Loss Fix.
- **perform_audit.php** — Exploratory subprocess auditor for Tier 1–3 `scripts/*.php` (excludes Tier 4/5, `repro_*`, `verify_*`, `_tmp_*`, `health.php`, `test_ajax.php`, `test_edit.php`). Shared runner: `scripts/lib/itm_perform_audit.php`. Truncates `error_log.txt`, isolates per-script log deltas, resolves Laragon `php.exe`, and writes `scripts/php_error_audit_results.json` with `exit_code` / `cli_errors` / `stdout_hits` / `summary`. Intentional exit-only failures: `scripts/data/perform_audit_allowlist.json`. Not a CI gate.
- **list_modules_not_on_sidebar.php** — Audits `modules/*/index.php` against live sidebar `match_dir` entries from `itm_sidebar_structure()` and active `modules_registry` rows without module folders. Shared builder: `scripts/lib/itm_list_modules_not_on_sidebar_report.php`. CLI exits `1` on unexpected gaps; policy-hidden registry rows are listed for reference only.
- **list_empty_tables.php** — Lists tenant-scoped tables with zero live rows for session `company_id` (browser Admin) or `--company=N` / `?company=N` filter (CLI/browser dropdown); module links open `modules/{table}/index.php` in a new tab when the folder exists.
- **verify_company_empty_sample_data.php** — CLI + browser (Admin) regression for tenant onboarding: reads `itm_list_empty_tables_collect_report()`, filters the 16-module **Add sample data** allowlist, runs `itm_seed_lookup_parents_for_table()` + `itm_seed_table_from_database_sql()` per empty table; `--company=N` / `?company=N` required (browser falls back to session `company_id`), optional `--module=slug` / `?module=slug`.
- **verify_tickets_sample_data.php** — CLI regression for tickets **Add sample data** on empty tenants without local employees (`TCK-0001`, `is_archived = 0`, active list count). Mutates company `4` ticket lookup rows during the run.
- **titles_list.php** — Scans all PHP files under `modules/` and extracts `<title>` tags. Shared helpers: `scripts/lib/itm_titles_list_audit.php`. Prints a summary count (match vs not match) for the canonical title `<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>`; non-matching rows are prefixed `[NOT MATCH]`.
- **titles_list_show.php** — Same scan and summary as `titles_list.php`, listing rendered inner title text; non-matching rows are prefixed `[NOT MATCH]`.
- **pitfalls.php** — Aggregates common pitfalls documented under section 10 from every `AGENT_NOTES.md` in the repository (modules, config, includes, scripts, phpunit, css, js, root, `.github`, etc.), with links to folders/notes and auto-backfilling of missing note files under `modules/` only. Browser (Admin) and CLI support (`-module=` / `--json`). Reviewed empty sections may record `[Confirmed] No pitfalls documented` (shown as documented confirmation, not the generic empty placeholder). Top-level upload roots (`files/`, `backups/`, `images/`, `tickets_photos/`, root `floor_plans/`) are skipped; `modules/floor_plans/` is still scanned.
## 8. Multi-Tenant Rules
- Maintenance scripts usually operate across all tenants or allow specifying a `company_id` via CLI arguments.

## 9. Audit Logging Requirements
- `check_audit_logs_coverage.php` is used to verify that mutations in other modules are correctly logged.

## 10. Common Pitfalls
- Running destructive scripts on the wrong environment. [Cursor-Valid]
- Forgetting to define `ITM_CLI_SCRIPT` when running PHP scripts from the command line. [Cursor-Valid]
- **Hardcoded seed user id 1:** repro/verify scripts must use `lib/itm_script_test_employee.php` for `employees` mutations — never UPDATE Admin reset tokens in place. Run `php scripts/check_script_disposable_employees.php` after changing audit repro scripts. [Cursor-Valid]
- **FK dropdown risk UI:** `detect_fk_dropdown_ui_risk_ui.php` is browser-only diagnostic output for cross-tenant FK findings, so keep the standard Admin browser gate (`lib/itm_script_regression_entry.php`) and ensure the visible/JSON summary reflects the active `risk_filter`, not only the raw unfiltered helper counts. [Cursor-Valid]
- **Resignations debug:** `debug_resignations_termination_date.php` defaults to `company_id=1` and `employee_id=0` (optional live row lookup). Module probe inserts a disposable employee when no live row is supplied — same weekly filter contract as `verify_employee_type_resignations.php`. Cross-month ISO weeks require the selected `month` to match `MONTH(termination_date)`. [Cursor-Valid]
- **Equipment create debug:** `debug_equipment_create_rollback_errno.php` — `mysqli_rollback()` clears `mysqli_errno()` on this stack; `create.php` must read DB errors before rollback or users see the generic save message. [Cursor-Valid]
- **MySQL 8 `NO_ZERO_DATE`:** do not use `<> '0000-00-00'` in resignations or verify SQL — use `itm_sql_valid_date_predicate()` from `includes/itm_date_format.php`. Symptom: `Incorrect DATE value: '0000-00-00'` on `mysqli_prepare` and an empty weekly report despite valid `termination_date` rows. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Running the Smoke Test
```bash
bash scripts/smoke_test.sh
```

### Running Unit Tests
```bash
php scripts/run_tests.php
php scripts/run_tests.php --coverage
```
Browser menu: `scripts/run_tests.php` — **Standard** or **HTML coverage** (`?run=1&mode=coverage`). Report: `phpunit/coverage/html/coverage.html`. See **`scripts/SCRIPTS.md` → PHPUnit test runner**.

### Bypassing Login for Debugging or Screenshots
This script is essential for rapid development, debugging errors as an Admin, or automating UI tasks like taking screenshots.
```bash
# Get a valid session ID for the Admin user
php scripts/bypass_login.php

# Get session for a specific user or company
php scripts/bypass_login.php --user=johndoe --company=2
```

### Resignations termination date debug
```bash
php scripts/debug_resignations_termination_date.php --date=18/06/2026 --company_id=1 --week=25 --month=6 --year=2026
```
Browser (login required): `scripts/debug_resignations_termination_date.php?date=18/06/2026&company_id=1&week=25&month=6&year=2026`. Listed in **`scripts/scripts.php`**.

## 12. Bypass Login (CLI Information)
The `scripts/bypass_login.php` script allows you to:
- **Faster Screenshots**: Quickly authenticate an automated browser (like Playwright) by setting the `PHPSESSID` cookie.
- **Debug as Admin**: Directly establish an authenticated state to test admin-only logic or view protected modules without manual login. **Admin users only** — non-admin `--user` values exit with an error.
- **Unlock Vault**: Automatically sets the `vault_key` session variable required for the Passwords module.
- **CLI Permissions**: The script automatically adjusts session file permissions (`0644`) so the web server (Apache) can read the session created in the CLI context.

### Usage with curl
```bash
# 1. Generate session
SESSION_ID=$(php scripts/bypass_login.php | grep "Session ID:" | awk '{print $3}')

# 2. Access protected page
curl -b "PHPSESSID=$SESSION_ID" http://localhost/dashboard.php
```

## 13. Module Owner Notes (Optional)
This directory is the toolbox for system administrators and developers.





## File Upload Modules

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

Legacy installs may still have `Private/{username}_{linked_user_id}/profile/`; `emp_profile_photo_serve_path()` falls back to that path when `employees.user_id` is set.

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
- **Security:** API blocks `Private` and `Departments` roots; UI uses `resolveScopedFolderPath()` for scoped navigation; trash operations are ACL-filtered; `downloadZip` blocks Home/`Common`/`Private`/`Departments`/`Trash` roots (scoped `Private/{username}_{employee_id}` backup allowed). Home shows virtual Trash only when the user has recoverable items; `listRecycle` uses leaf filter (`explorer_filter_trash_list_to_leaf_items`). See `modules/explorer/AGENT_NOTES.md` and **`AGENTS.md` → Explorer module**.
- **Implementation:** Standard `.itm-photo-upload-target` UI; desktop drag-and-drop upload. All folder creation uses `itm_ensure_files_storage_directory()` / `explorer_ensure_dir()`. Block dotfile uploads; managed `.htaccess` overwrites malicious uploads on ensure.
- **Regression scripts:** `php scripts/test_explorer_paths.php`, `php scripts/verify_explorer_zip_leak.php` (two-step ZIP contract); `.htaccess` RCE PoC: `verify_explorer_rce_htaccess.php`, `verify_explorer_rce_marker.php`; Import data loss: `repro_employee_dataloss.php`, `repro_generic_dataloss.php`.

### 10. Private Contacts
- **Paths:** `modules/private_contacts/create.php`, `modules/private_contacts/edit.php`
- **Storage:** `files/{company_id}/Private/{username}_{employee_id}/private_contacts/` (`deny_http` chain)
- **Description:** PNG contact photos.
- **Implementation:** Creates storage via `itm_ensure_files_storage_directory()`; UI serves images through `itm_files_serve_url()` → `modules/explorer/file.php`.

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


## Recent Changes (Maintenance Task)

- **verify_port_visualizer_layout.php** — CLI regression for Vertical vs Horizontal grid metadata and port 2 placement (`grid-row` / `grid-column`).
- **Standardized Output**: Focus scripts (`benchmark_user_config.php`, `repro_explorer_traversal.php`, `verify_explorer_fix*` suite, `repro_rce.php`, `repro_bac.php`, `repro_sqli.php`, `benchmark_stats_optimized.php`, `idf_device_port_sort_test.php`, `crud_tables.php`, `crud_titles.php`, `crud_actions.php`, `test_visualizer_v2.php`, and `repro_bug.php`) refactored to use `scripts/lib/script_cli_output.php` and `itm_script_output_begin()` for consistent CLI/Browser reporting.
- **Obsolete Directory Removal**: All references to the non-existent `fixed_files/` directory have been removed from all functional scripts. These now target the live `modules/` directory.
- **Path and Include Audit**: Fixed relative path issues in multiple scripts (`repro_rce.php`, `repro_bac.php`, `repro_sqli.php`, `generate_tests.php`) to ensure they correctly resolve dependencies from the `scripts/` directory.
- **Catalog Sync**: Updated `scripts/scripts.php` to include missing reproduction and verification scripts while maintaining the mandatory "Deployment & Git" section. Fixed broken links and added appropriate access badges.
- **Bug Fixes**: Identified and developed a fix for a regression in `EquipmentBespokeTest.php` and `equipment_delete_clear_table_test.php` where soft-deleted records (with `deleted_at`) were being incorrectly counted as active during tests. *Note: The fix for `EquipmentBespokeTest.php` was developed but remains uncommitted to strictly adhere to the directory constraint.*
- **System-Wide Path & Include Verification**: Performed a rigorous audit of all file path and `require`/`include` context behaviors. Confirmed that relative file references correctly resolve using parent/child directories (via proper `chdir()` context switches where relevant). Verified complete removal of obsolete `fixed_files/` directory, cementing `modules/` as the sole authoritative code target for reproduction and verification scripts.
