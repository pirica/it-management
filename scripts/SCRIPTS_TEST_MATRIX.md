# Scripts full test matrix

> Canonical verification map for **cataloged** entries in `scripts/scripts.php`.
> Do **not** use `perform_audit.php` as a quality gate. Prefer this matrix + existing runners.
>
> Standards: `scripts/SCRIPTS.md`. Catalog UI: `scripts/scripts.php`.

Generated from catalog on 2026-07-16. Catalog rows classified: **234**.

| Tier | Count | Purpose |
|------|------:|---------|
| 0 | 4 | Docs / reference only |
| 1 | 7 | Always-safe baseline (CI parity) |
| 2 | 20 | Manual static `check_*` cluster |
| 3 | 150 | Runtime `verify_*` / `repro_*` / diagnostics |
| 4 | 16 | Browser / human-flow / MBQA |
| 5 | 37 | Excluded from blanket runs (destructive / maintenance) |

## Runner coverage map

| Runner | Covers | Does not cover |
|--------|--------|----------------|
| `bash scripts/smoke_test.sh` | PHP lint; `check_csrf_coverage.php`; `check_sql_injection_coverage.php`; `check_fk_label_search_coverage.php` | MySQL, PHPUnit, MBQA, most `check_*` / `verify_*` |
| `php scripts/run_tier2_checks.php` | All Tier 2 static `check_*` scripts from this matrix (parse or fallback list) | Tier 1 smoke trio, Tier 3+ runtime verifiers, MBQA |
| `bash scripts/verify_database_sql_import.sh` | Full `database.sql` import + table count; calls schema verify | Module HTTP behaviour |
| `php scripts/verify_crud_fk_label_search.php` | Runtime FK label search (CI database-import) | Non-search modules |
| `php scripts/run_tests.php` | PHPUnit suite under `phpunit/tests/Unit/` | Module entry HTTP flows; most `scripts/verify_*` |
| `php scripts/module_browser_qa_runner.php` | HTTP CRUD matrix across modules/companies | `scripts/` verifiers; deep bespoke Tier D modules |
| `php scripts/verify_system_status.php` | System Status layout + Windows `test_*.php` wrappers | Non-system-status scripts |
| `php scripts/perform_audit.php` | Exploratory crash scan of many scripts | **Not a pass/fail gate** - can mutate DB |

## Destroy -> document -> fresh clone protocol

If any script **destroys or corrupts** the live `itmanagement` database, seed data, or critical local trees so later scripts cannot run:

1. **Stop** the matrix batch.
2. **Document** the culprit in `scripts/data/scripts-matrix-destroy-log.md` (and mark the matrix row `DESTROYED_ENV`).
3. **Fresh clone** the database from `database.sql` (do not continue on partial state).
4. **Sanity-check**, then resume at the **next** script (or re-run only if the failure was a false alarm).

### Detect destruction

- Table count far below expected (see `scripts/number_db_tables.txt` / `CREATE TABLE` count in `database.sql`)
- Seed admins / companies wiped; login broken
- Constraint storms leaving tenants unusable
- Core rows deleted beyond disposable test cleanup
- `files/` or upload trees rendered unusable

### Document fields

| Field | Example |
|-------|---------|
| Script | `scripts/force_delete_company.php` |
| Command | `php scripts/force_delete_company.php --id=3` |
| Tier | 5 (or the tier that was running) |
| Timestamp | ISO-8601 |
| Symptom | companies table empty / login fails |
| Exit / tail | last 20 lines + exit code |
| Status | `DESTROYED_ENV` |

### Fresh clone (Windows Laragon)

```cmd
cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
"C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -pitmanagement --default-character-set=utf8mb4 < database.sql
```

Or: `bash scripts/verify_database_sql_import.sh`

Then: `php scripts/verify_database_schema.php` (or `php scripts/count_db_tables.php`) and re-run Tier 1 smoke once before continuing.

If tracked repo files were rewritten: `git checkout -- <paths>` (or a fresh worktree) and log those paths the same way.

### Resume rules

- Never start Tier 4 on a half-destroyed DB
- After restore, confirm Tier 1 is green once
- Keep every `DESTROYED_ENV` entry in the destroy log for the final report

## Run order and report format

### Order

1. **Tier 1** - baseline (includes intentional DB re-import via `verify_database_sql_import.sh`)
2. **Tier 2** - static `check_*` (batch; no DB mutation expected)
3. **Tier 3** - runtime verifiers in subsystem batches (Schema, Security, Explorer, Email/Auth, Dashboard/Ops)
4. On any `DESTROYED_ENV` -> document -> fresh clone -> sanity -> resume
5. **Tier 4** - only on a healthy clone with Apache + MySQL ready
6. **Tier 5** - report as excluded; run only under isolated/explicit approval

### Result statuses

| Status | Meaning |
|--------|---------|
| `PASS` | Exit 0 and expected PASS/OK output |
| `FAIL` | Exit non-zero or assertion failure |
| `SKIP` | Prerequisite missing (SMTP, PowerShell, Apache) |
| `EXCLUDED` | Tier 5 - not run in blanket pass |
| `DESTROYED_ENV` | Script wrecked DB/repo; clone required |
| `COVERED` | Already exercised by a meta-runner in this session |

### Report template (per batch)

```text
tier=N batch=<name> started=<iso>
script=<file> status=PASS|FAIL|SKIP|EXCLUDED|DESTROYED_ENV|COVERED exit=<code> note=...
...
tier=N batch=<name> finished=<iso> pass=X fail=Y skip=Z destroyed=W
```

Optional destroy append-only log: `scripts/data/scripts-matrix-destroy-log.md`.
Latest safe-matrix run report (A–Z lists): `scripts/data/scripts_errors.txt`.

## Tier 1 commands (always)

```bash
bash scripts/smoke_test.sh
bash scripts/verify_database_sql_import.sh
php scripts/verify_crud_fk_label_search.php
php scripts/run_tests.php
```

Windows Laragon PowerShell (PHP binary):

```powershell
$php = "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe"
& $php scripts\verify_crud_fk_label_search.php
& $php scripts\run_tests.php
```

## Tier 2 commands (static cluster)

**Batch (recommended):**

```bash
php scripts/run_tier2_checks.php
php scripts/run_tier2_checks.php --continue
```

Individual scripts:

```bash
php scripts/check_ui_action_emoji.php
php scripts/check_codacy_xss_echo.php
php scripts/check_not_operator.php
php scripts/check_crud_audit_soft_delete.php
php scripts/check_audit_logs_coverage.php
php scripts/check_crud_rbac_coverage.php
php scripts/check_ui_configuration_coverage.php
php scripts/check_display_field_columns_search.php
php scripts/check_index_table_compliance.php
php scripts/check_script_disposable_employees.php
php scripts/check_stale_user_id_sql.php
php scripts/check_stale_user_terminology.php
php scripts/check_multi_tenant_leaks.php
php scripts/check_database_sql_company_name_uniques.php
php scripts/check_standard_crud_delegate_requires.php
php scripts/check_employees_clear_table_transaction.php
php scripts/check_equipment_clear_table_delete.php
# plus remaining Tier 2 rows in the catalog table below
```

## Tier 4 commands (heavy - after healthy clone)

```bash
php scripts/module_browser_qa_runner.php --module=<slug> --company=1
php scripts/idfs_sync_human_test.php
php scripts/explorer_human_test.php
php scripts/auth_register_reset_human_test.php
php scripts/equipment_delete_clear_table_test.php
php scripts/employees_delete_clear_table_test.php
```

## Full catalog classification

| Tier | Script | Prerequisites | Risk | Covered by | Notes |
|------|--------|---------------|------|------------|-------|
| 0 | `SCRIPTS.md` | read-only | none | docs-only | Documentation / reference (not a regression gate) |
| 0 | `api.php` | read-only | none | docs-only | Documentation / reference (not a regression gate) |
| 0 | `itm-user-errors.js` | read-only | none | docs-only | Documentation / reference (not a regression gate) |
| 0 | `pitfalls.php` | read-only | none | docs-only | Documentation / reference (not a regression gate) |
| 1 | `check_csrf_coverage.php` | PHP | none | CI smoke | Invoked by smoke_test.sh |
| 1 | `check_fk_label_search_coverage.php` | PHP | none | CI smoke | Invoked by smoke_test.sh |
| 1 | `check_sql_injection_coverage.php` | PHP | none | CI smoke | Invoked by smoke_test.sh |
| 1 | `run_tests.php` | MySQL optional | low | PHPUnit meta | Unit/integration suite under phpunit/tests/Unit |
| 1 | `smoke_test.sh` | PHP | none | CI smoke | Lint + CSRF + SQLi + FK label static |
| 1 | `verify_crud_fk_label_search.php` | MySQL | low | CI database-import | Runtime FK label search after import |
| 1 | `verify_database_sql_import.sh` | MySQL | destroys-DB | CI database-import | Full database.sql re-import — baseline clone |
| 2 | `check_audit_logs_coverage.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_codacy_xss_echo.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_manual_sql_string.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_not_operator.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_crud_audit_soft_delete.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_crud_rbac_coverage.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_database_sql_company_name_uniques.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_delimiters.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_display_field_columns_search.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_duplicates.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_employees_clear_table_transaction.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_equipment_clear_table_delete.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_index_table_compliance.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_multi_tenant_leaks.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_phones.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_points.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_script_disposable_employees.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_sql_errors.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_stale_user_id_sql.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_stale_user_terminology.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_standard_crud_delegate_requires.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_ui_action_emoji.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 2 | `check_ui_configuration_coverage.php` | PHP | none | static-manual | Pre-merge static gate (not in smoke) |
| 3 | `DBdesign.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `analyze_database_health.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `apitest_tier_basic.php` | MySQL | low | runtime | API rate-limit tier regression |
| 3 | `apitest_tier_free.php` | MySQL | low | runtime | API rate-limit tier regression |
| 3 | `benchmark_sidebar_module_access.php` | MySQL | low | runtime | Read-mostly performance probe |
| 3 | `benchmark_stats_optimized.php` | MySQL | low | runtime | Read-mostly performance probe |
| 3 | `benchmark_user_config.php` | MySQL | low | runtime | Read-mostly performance probe |
| 3 | `compare_database_sql_modules.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `count_args.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `count_db_tables.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `crud_actions.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `crud_tables.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `crud_titles.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `db_field_active.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `debug.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `debug_resignations_termination_date.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `detect_fk_dropdown_ui_risk_ui.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `employee_fields_missing.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `fields_missing.php` | MySQL | low | runtime | All-module schema/UI audit (`--module=` / `--json` / `--strict-gate`) |
| 3 | `fields_missing_reviewed.php` | PHP | low | runtime | Reviewed bespoke gate manifest (`fields_missing_reviewed.json`; `--json`) |
| 3 | `extract_by_fields.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `generate_FK_employee_id.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `generate_reassignment.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `health.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `idf_device_port_sort_test.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `idfs_api_payload_dry_run.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `list_active_and_checkboxes.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `list_boolean_integer_fields.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `list_enum_fields.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `list_modules_not_on_sidebar.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `list_phone_columns.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `repro_attempts_data_leak_v2.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_audit_disclosure.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_audit_token_leak.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_auth_bypass_v3.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_bac.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_bac_updated.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_birthdays_resignations_rbac.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_bug.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_contacts_idor.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_cross_tenant_admin.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_db_integrity.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_destructive_import.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_employee_companies_bac.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_employee_companies_leak.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_employee_dataloss.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_equip_issues.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_esa_vulnerability.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_explorer_path_bypass_v4.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_explorer_traversal.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_explorer_zip_slip_v2.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_generic_dataloss.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_notes_idor.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_notes_traversal.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_rbac_bypass.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_rce.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_rce_updated.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_request_password_bypass.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_select_options.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_select_options_rbac.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_select_options_unauthorized_v2.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_sqli.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_sqli_updated.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_status_leak.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_todo_user_leak.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_vault_corruption.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_visitors_bac.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_visitors_sqli.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_vulnerabilities.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `repro_zip_slip.php` | MySQL | low | runtime | Security PoC / regression (disposable users) |
| 3 | `schema_report.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `sql_injection_matrix_test.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `system_status_api.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `system_status_phpinfo.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_ajax.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_cpu_usage.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_db_error_messages.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_disk_usage.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_edit.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_email_forgot.php` | SMTP | low | runtime | Sends mail - external dependency |
| 3 | `test_employee_id-foreign_keys.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_explorer_paths.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_explorer_preview.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_form_failed_save_display.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_import_user_samples.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_mysql_databases.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_mysql_size.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_mysql_status.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_mysql_version.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_notes_human.py` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_php_extensions.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_php_ini_values.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_php_version.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_ram_usage.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_register_mail.php` | SMTP | low | runtime | Sends mail - external dependency |
| 3 | `test_session.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_sql_injection.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `test_system_info.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_uptime.php` | Admin+host | low | runtime | System Status stack / PowerShell wrappers |
| 3 | `test_visualizer_v2.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `titles_list.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `titles_list_show.php` | PHP | low | runtime | Read-mostly listing / diagram tool |
| 3 | `update_display.py` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `validate_DB_schema.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `validate_delete_employee.php` | MySQL | low | runtime | Runtime verify / repro / diagnostic |
| 3 | `verify_api_coverage.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_audit_logs_disclosure.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_audit_updated.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_auto_scaffolding.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_clear_table_fix.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_company_deletion.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_company_module_access.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_dashboard_active_employees.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_dashboard_online_employees.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_database_schema.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_dnd.py` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_emails_module.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_employee_type_resignations.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_employees_equipment_search_coverage.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_employees_sensitive_view.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_equipment_triggers.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_fix.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_fix_standalone.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_fix_updated.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_fix_web.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_rce_htaccess.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_rce_marker.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_updated.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_explorer_zip_leak.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_import_fix_updated.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_invitations_escalation.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_json_import_validation.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_maintenance_scripts_rbac.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_metadata_column_cache.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_notes_ajax_contract.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_notes_ui.py` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_chatbot.php` | MySQL | low | runtime | Chatbot + knowledge_base contract verifier |
| 3 | `verify_ops_report.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_rack_planner.php` | MySQL | low | runtime | Rack planner price source sync verifier |
| 3 | `verify_reports_hub.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_request_password.php` | MySQL | low | runtime | Request Password workflow + delete guard verifier |
| 3 | `verify_password_reset_flow.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_rbac_updated.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_roles_permissions.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_select_options_escalation.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_sql.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_sqli_updated.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_status_leak_fixed.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_system_status.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_update_port_zero_row.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_user_config_profile.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_user_idor.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_visitors_bac_fix.php` | MySQL | low | runtime | Domain regression verifier |
| 3 | `verify_visitors_sqli_fix.php` | MySQL | low | runtime | Domain regression verifier |
| 4 | `auth_register_reset_human_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `employees_delete_clear_table_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `equipment_delete_clear_table_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `explorer_human_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `floor_designer_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `floor_plans_folder_move_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `idfs_sync_human_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `module_browser_qa_build_report.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `module_browser_qa_runner.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `module_clean_tests_qa_runner.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `take_screenshots_modules.py` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `take_screenshots_modules_all.py` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `take_screenshots_passwords.py` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `tickets_related_equipment_delete_test.php` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `verify_todo.py` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 4 | `verify_todo_categories.py` | Apache+MySQL | mutates-DB | human/MBQA | Heavy integration - isolate from Tier 2/3 batches |
| 5 | `apply_bulk_actions_records_per_page_gate.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_bulk_delete_cancel_ux.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_crud_audit_soft_delete.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_crud_fk_label_search.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_crud_hidden_employee_id_alias.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_crud_rbac_guards.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_date_display_format.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_display_field_columns_search_alias.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_form_failed_save_display_fix.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_human_friendly_error_display.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_itm_actions_cell_markers.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `apply_module_sample_data_seed.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Writes database.sql seed blocks |
| 5 | `apply_ui_action_emoji.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `bypass_login.php` | isolated-env | destructive | excluded-blanket | Dev session hijack |
| 5 | `bypass_v2.php` | isolated-env | destructive | excluded-blanket | Dev session hijack |
| 5 | `cleanup_equipment_test_module_artifacts.php` | isolated-env | destructive | excluded-blanket | Deletes test companies and scaffold folders |
| 5 | `delete_clone_employee.php` | isolated-env | destructive | excluded-blanket | Deletes cloned employee trees |
| 5 | `detect_fk_dropdown_ui_risk.php` | isolated-env | destructive | excluded-blanket | Safe by default; --repair-catalogs is destructive |
| 5 | `empty_folders.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Overwrites index.html / .htaccess tree-wide |
| 5 | `ensure_equipment_type_modules.php` | isolated-env | destructive | excluded-blanket | Creates is_* module folders |
| 5 | `ensure_files_htaccess_chain.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Overwrites files/ hardening chain |
| 5 | `export_floor_plan_folders_seed.php` | isolated-env | destructive | excluded-blanket | Dump-only helper (stdout); keep out of blanket mutation passes |
| 5 | `fix_sql.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `fix_sql_broad.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `fix_sql_departments.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `force_delete_company.php` | isolated-env | destructive | excluded-blanket | Wipes company data |
| 5 | `generate_tests.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Writes generated PHPUnit files |
| 5 | `identify_modules.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Writes modules_metadata.json |
| 5 | `normalize_database_sql_created_at.php` | isolated-env | writes-repo-or-DB | excluded-blanket | Maintenance / bulk patcher - dry-run or isolated only |
| 5 | `perform_audit.php` | isolated-env | destructive | excluded-blanket | Subprocess-runs all scripts - unsafe umbrella |
| 5 | `repair_table_from_schema.php` | isolated-env | destructive | excluded-blanket | Schema repair writes |
| 5 | `run_email_alert_rules.php` | isolated-env | destructive | excluded-blanket | Sends live emails when scheduled |
| 5 | `seed_company_module_access.php` | isolated-env | destructive | excluded-blanket | Seeds CMA rows |
| 5 | `sql_insert.php` | isolated-env | destructive | excluded-blanket | Admin raw INSERT |
| 5 | `sync_modules_registry.php` | isolated-env | destructive | excluded-blanket | Upserts modules_registry / CMA |
| 5 | `transfer_data_from_employee.php` | isolated-env | destructive | excluded-blanket | Clones/mutates employee-related rows |
| 5 | `update_all_created_at.php` | isolated-env | destructive | excluded-blanket | Bulk UPDATE all created_at (use --dry-run only in blanket plans) |

## Tier 5 exclusion list (summary)

Blanket plans must **not** execute these without an isolated disposable environment and an explicit operator:

- Destructive DB: `force_delete_company.php`, `transfer_data_from_employee.php`, `delete_clone_employee.php`, `cleanup_equipment_test_module_artifacts.php`, `sql_insert.php`, `update_all_created_at.php` (non-dry-run), `detect_fk_dropdown_ui_risk.php --repair-catalogs`
- Repo / tree writers: all `apply_*`, `fix_sql*`, `empty_folders.php`, `ensure_files_htaccess_chain.php`, `generate_tests.php`, `identify_modules.php`, `apply_module_sample_data_seed.php`
- Unsafe umbrella: `perform_audit.php`
- Dev / deploy danger: `bypass_login.php`, `bypass_v2.php`, `reset_git_history.php`
- Live side effects: `run_email_alert_rules.php` (unless intentionally testing SMTP)

## Maintenance

When adding a catalog row in `scripts/scripts.php`, add or re-classify it in this matrix in the same PR. Re-run classification by regenerating this file from the catalog if the helper is kept locally; otherwise update the table manually.

