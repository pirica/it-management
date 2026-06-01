# Module browser QA — 2026-05-31

## Summary

- Environment: `http://localhost/it-management/` (Laragon)
- Auth: Admin / Admin
- Companies: 5 (TechCorp Global … Enterprise IT)
- Step outcomes: **23 Pass**, **1 Fail**
- Modules in this report: 1
- Runner: `php scripts/module_browser_qa_runner.php` or browser form at `scripts/module_browser_qa_runner.php`
- Bulk delete / Clear table: N/A when row count &lt; `records_per_page` (25)

### QA runner tier reference

Modules in **`$bespokeSmoke` (Tier D)** run navigation smoke only: `list`, `search`, and `sort` on the index; other steps are recorded as Pass with notes `N/A smoke`, `Skip (bespoke smoke)`, or `N/A`.

| Runner variable | Modules |
|---|---|
| `$bespokeSmoke` | `budget_report`, `expiring`, `rack_planner`, `floor_plans`, `companies` |
| `$skipClear` | `companies`, `users` |

**`$skipClear`:** tenant FK-aware clear is never run on these tables (shared auth). Tier D modules also skip the start-of-module clear step with note `Skip (bespoke smoke)`.

### Skipped steps (configured exceptions — counted as Pass/N/A)

| Module | Step | Label | Reason |
|---|---|---|---|
| user_companies | `create` | Create form | N/A (module has no create screen) |
| user_companies | `add` | Bulk random rows | N/A (no random bulk rows for junction assignments) |
| user_companies | `import_db` | Import Excel | N/A (no Excel import round-trip) |
| patches_updates | `sample_data` | Sample data | No sample rows found in database.sql for this module. |
| explorer | `ui_check` | Table Actions UI | N/A (Not standart Module CRUD) |
| explorer | `clear` | Tenant clear | N/A (Not standart Module CRUD) |
| explorer | `sample_data` | Sample data | N/A (Not standart Module CRUD) |
| explorer | `add` | Bulk random rows | N/A (Not standart Module CRUD) |
| explorer | `pagination` | Pagination | N/A (Not standart Module CRUD) |
| explorer | `bulk_cancel` | Bulk Cancel UI | N/A (Not standart Module CRUD) |
| explorer | `bulk_delete` | Bulk delete | N/A (Not standart Module CRUD) |
| explorer | `search` | Search | N/A (Not standart Module CRUD) |
| explorer | `sort` | Sort links | N/A (Not standart Module CRUD) |
| explorer | `create` | Create form | N/A (Not standart Module CRUD) |
| explorer | `view` | View record | N/A (Not standart Module CRUD) |
| explorer | `edit` | Edit form | N/A (Not standart Module CRUD) |
| explorer | `list_all` | List all | N/A (Not standart Module CRUD) |
| explorer | `export_pdf` | Export PDF | N/A (Not standart Module CRUD) |
| explorer | `export_xlsx` | Export Excel (.xlsx) | N/A (Not standart Module CRUD) |
| explorer | `clear_table` | Clear table | N/A (Not standart Module CRUD) |
| explorer | `import_db` | Import Excel | N/A (Not standart Module CRUD) |
| explorer | `single_delete` | Single delete | N/A (Not standart Module CRUD) |
| employee_system_access | `sample_data` | Sample data | N/A (Auto populated) |
| employee_system_access | `create` | Create form | N/A (Auto populated) |
| employee_system_access | `bulk_cancel` | Bulk Cancel UI | N/A (Auto populated) |
| employee_system_access | `bulk_delete` | Bulk delete | N/A (Auto populated) |
| employee_system_access | `list_all` | List all | N/A (Auto populated) |
| employee_system_access | `clear_table` | Clear table | N/A (Auto populated) |
| employee_system_access | `single_delete` | Single delete | N/A (Auto populated) |
| employee_assignment_history | `sample_data` | Sample data | No sample rows found in database.sql for this module. |
| approvers | `sample_data` | Sample data | No sample rows found in database.sql for this module. |
| ip_addresses | `sample_data` | Sample data | N/A (IP addresses are generated from subnets, not database.sql samples) |
| equipment_types | `add` | Bulk random rows | N/A (Bulk random rows — avoid module creations) |
| system_access | `clear_table` | Clear table | N/A (Auto populated) |
| system_access | `sample_data` | Sample data | N/A (Auto populated) |
| idf_links | `sample_data` | Sample data | No sample rows found in database.sql for this module. |
| idf_ports | `sample_data` | Sample data | No sample rows found in database.sql for this module. |
| idf_positions | `sample_data` | Sample data | No sample rows found in database.sql for this module. |
| users | `clear` | Tenant clear | N/A (users module is user creation) |
| users | `sample_data` | Sample data | N/A (users module is user creation) |
| users | `add` | Bulk random rows | N/A (users module is user creation) |
| users | `bulk_cancel` | Bulk Cancel UI | N/A (users module is user creation) |
| users | `bulk_delete` | Bulk delete | N/A (users module is user creation) |
| users | `clear_table` | Clear table | N/A (users module is user creation) |
| users | `single_delete` | Single delete | N/A (users module is user creation) |
| ui_configuration | `add` | Bulk random rows | N/A (User UI Configurarion capped by 1) |
| ui_configuration | `create` | Create form | N/A (User UI Configurarion) |
| ui_configuration | `pagination` | Pagination | N/A (User UI Configurarion capped by 1) |
| ui_configuration | `bulk_cancel` | Bulk Cancel UI | N/A (User UI Configurarion) |
| ui_configuration | `bulk_delete` | Bulk delete | N/A (User UI Configurarion) |
| settings | `mysql` | database.sql seed rows | N/A (settings module is configuration/backup UI) |
| settings | `ui_check` | Table Actions UI | N/A (settings module is configuration/backup UI) |
| settings | `clear` | Tenant clear | N/A (settings module is configuration/backup UI) |
| settings | `sample_data` | Sample data | N/A (settings module is configuration/backup UI) |
| settings | `add` | Bulk random rows | N/A (settings module is configuration/backup UI) |
| settings | `pagination` | Pagination | N/A (settings module is configuration/backup UI) |
| settings | `bulk_cancel` | Bulk Cancel UI | N/A (settings module is configuration/backup UI) |
| settings | `bulk_delete` | Bulk delete | N/A (settings module is configuration/backup UI) |
| settings | `search` | Search | N/A (settings module is configuration/backup UI) |
| settings | `sort` | Sort links | N/A (settings module is configuration/backup UI) |
| settings | `create` | Create form | N/A (settings module is configuration/backup UI) |
| settings | `view` | View record | N/A (settings module is configuration/backup UI) |
| settings | `edit` | Edit form | N/A (settings module is configuration/backup UI) |
| settings | `list_all` | List all | N/A (settings module is configuration/backup UI) |
| settings | `clear_table` | Clear table | N/A (settings module is configuration/backup UI) |
| settings | `import_db` | Import Excel | N/A (settings module is configuration/backup UI) |
| settings | `single_delete` | Single delete | N/A (settings module is configuration/backup UI) |
| audit_logs | `list` | List page | N/A (read-only audit centre) |
| audit_logs | `add` | Bulk random rows | N/A (read-only audit centre) |
| audit_logs | `create` | Create form | N/A (read-only audit centre) |
| audit_logs | `edit` | Edit form | N/A (read-only audit centre) |
| audit_logs | `list_all` | List all | N/A (read-only audit centre) |
| audit_logs | `bulk_cancel` | Bulk Cancel UI | N/A (no bulk-delete form in HTML) |
| audit_logs | `bulk_delete` | Bulk delete | N/A (read-only audit centre; delete disabled) |
| audit_logs | `clear_table` | Clear table | N/A (read-only audit centre; delete disabled) |
| audit_logs | `import_db` | Import Excel | N/A (read-only audit centre) |
| audit_logs | `single_delete` | Single delete | N/A (read-only audit centre; delete disabled) |
| audit_logs | `sample_data` | Sample data | N/A (read-only audit centre) |
| attempts | `clear` | Tenant clear | N/A (global audit table) |
| attempts | `sample_data` | Sample data | N/A (global audit table) |
| attempts | `add` | Bulk random rows | N/A (global audit table) |
| attempts | `pagination` | Pagination | N/A (global audit table) |
| attempts | `bulk_cancel` | Bulk Cancel UI | N/A (global audit table) |
| attempts | `bulk_delete` | Bulk delete | N/A (global audit table) |
| attempts | `search` | Search | N/A (global audit table) |
| attempts | `sort` | Sort links | N/A (global audit table) |
| attempts | `create` | Create form | N/A (global audit table) |
| attempts | `view` | View record | N/A (global audit table) |
| attempts | `edit` | Edit form | N/A (global audit table) |
| attempts | `list_all` | List all | N/A (global audit table) |
| attempts | `export_pdf` | Export PDF | N/A (global audit table) |
| attempts | `export_xlsx` | Export Excel (.xlsx) | N/A (global audit table) |
| attempts | `clear_table` | Clear table | N/A (global audit table) |
| attempts | `import_db` | Import Excel | N/A (global audit table) |
| attempts | `single_delete` | Single delete | N/A (global audit table) |
| user_sidebar_preferences | `sample_data` | Sample data | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `add` | Bulk random rows | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `pagination` | Pagination | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `bulk_cancel` | Bulk Cancel UI | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `bulk_delete` | Bulk delete | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `create` | Create form | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `view` | View record | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `edit` | Edit form | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `export_pdf` | Export PDF | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `export_xlsx` | Export Excel (.xlsx) | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `clear_table` | Clear table | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `import_db` | Import Excel | N/A (seeds default sidebar layout) |
| user_sidebar_preferences | `single_delete` | Single delete | N/A (seeds default sidebar layout) |
| is_workstation | `clear` | Tenant clear | N/A routing |
| is_workstation | `sample_data` | Sample data | N/A routing |
| is_workstation | `add` | Bulk random rows | N/A routing |
| is_workstation | `pagination` | Pagination | N/A routing |
| is_workstation | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_workstation | `bulk_delete` | Bulk delete | N/A routing |
| is_workstation | `search` | Search | N/A routing |
| is_workstation | `sort` | Sort links | N/A routing |
| is_workstation | `create` | Create form | N/A routing |
| is_workstation | `view` | View record | N/A routing |
| is_workstation | `edit` | Edit form | N/A routing |
| is_workstation | `list_all` | List all | N/A routing |
| is_workstation | `export_pdf` | Export PDF | N/A routing |
| is_workstation | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_workstation | `import_db` | Import Excel | N/A routing |
| is_workstation | `single_delete` | Single delete | N/A routing |
| is_workstation | `clear_table` | Clear table | N/A routing |
| is_server | `clear` | Tenant clear | N/A routing |
| is_server | `sample_data` | Sample data | N/A routing |
| is_server | `add` | Bulk random rows | N/A routing |
| is_server | `pagination` | Pagination | N/A routing |
| is_server | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_server | `bulk_delete` | Bulk delete | N/A routing |
| is_server | `search` | Search | N/A routing |
| is_server | `sort` | Sort links | N/A routing |
| is_server | `create` | Create form | N/A routing |
| is_server | `view` | View record | N/A routing |
| is_server | `edit` | Edit form | N/A routing |
| is_server | `list_all` | List all | N/A routing |
| is_server | `export_pdf` | Export PDF | N/A routing |
| is_server | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_server | `import_db` | Import Excel | N/A routing |
| is_server | `single_delete` | Single delete | N/A routing |
| is_server | `clear_table` | Clear table | N/A routing |
| is_switch | `clear` | Tenant clear | N/A routing |
| is_switch | `sample_data` | Sample data | N/A routing |
| is_switch | `add` | Bulk random rows | N/A routing |
| is_switch | `pagination` | Pagination | N/A routing |
| is_switch | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_switch | `bulk_delete` | Bulk delete | N/A routing |
| is_switch | `search` | Search | N/A routing |
| is_switch | `sort` | Sort links | N/A routing |
| is_switch | `create` | Create form | N/A routing |
| is_switch | `view` | View record | N/A routing |
| is_switch | `edit` | Edit form | N/A routing |
| is_switch | `list_all` | List all | N/A routing |
| is_switch | `export_pdf` | Export PDF | N/A routing |
| is_switch | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_switch | `import_db` | Import Excel | N/A routing |
| is_switch | `single_delete` | Single delete | N/A routing |
| is_switch | `clear_table` | Clear table | N/A routing |
| is_printer | `clear` | Tenant clear | N/A routing |
| is_printer | `sample_data` | Sample data | N/A routing |
| is_printer | `add` | Bulk random rows | N/A routing |
| is_printer | `pagination` | Pagination | N/A routing |
| is_printer | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_printer | `bulk_delete` | Bulk delete | N/A routing |
| is_printer | `search` | Search | N/A routing |
| is_printer | `sort` | Sort links | N/A routing |
| is_printer | `create` | Create form | N/A routing |
| is_printer | `view` | View record | N/A routing |
| is_printer | `edit` | Edit form | N/A routing |
| is_printer | `list_all` | List all | N/A routing |
| is_printer | `export_pdf` | Export PDF | N/A routing |
| is_printer | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_printer | `import_db` | Import Excel | N/A routing |
| is_printer | `single_delete` | Single delete | N/A routing |
| is_printer | `clear_table` | Clear table | N/A routing |
| is_pos | `clear` | Tenant clear | N/A routing |
| is_pos | `sample_data` | Sample data | N/A routing |
| is_pos | `add` | Bulk random rows | N/A routing |
| is_pos | `pagination` | Pagination | N/A routing |
| is_pos | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_pos | `bulk_delete` | Bulk delete | N/A routing |
| is_pos | `search` | Search | N/A routing |
| is_pos | `sort` | Sort links | N/A routing |
| is_pos | `create` | Create form | N/A routing |
| is_pos | `view` | View record | N/A routing |
| is_pos | `edit` | Edit form | N/A routing |
| is_pos | `list_all` | List all | N/A routing |
| is_pos | `export_pdf` | Export PDF | N/A routing |
| is_pos | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_pos | `import_db` | Import Excel | N/A routing |
| is_pos | `single_delete` | Single delete | N/A routing |
| is_pos | `clear_table` | Clear table | N/A routing |
| is_router | `clear` | Tenant clear | N/A routing |
| is_router | `sample_data` | Sample data | N/A routing |
| is_router | `add` | Bulk random rows | N/A routing |
| is_router | `pagination` | Pagination | N/A routing |
| is_router | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_router | `bulk_delete` | Bulk delete | N/A routing |
| is_router | `search` | Search | N/A routing |
| is_router | `sort` | Sort links | N/A routing |
| is_router | `create` | Create form | N/A routing |
| is_router | `view` | View record | N/A routing |
| is_router | `edit` | Edit form | N/A routing |
| is_router | `list_all` | List all | N/A routing |
| is_router | `export_pdf` | Export PDF | N/A routing |
| is_router | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_router | `import_db` | Import Excel | N/A routing |
| is_router | `single_delete` | Single delete | N/A routing |
| is_router | `clear_table` | Clear table | N/A routing |
| is_port_patch_panel | `clear` | Tenant clear | N/A routing |
| is_port_patch_panel | `sample_data` | Sample data | N/A routing |
| is_port_patch_panel | `add` | Bulk random rows | N/A routing |
| is_port_patch_panel | `pagination` | Pagination | N/A routing |
| is_port_patch_panel | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_port_patch_panel | `bulk_delete` | Bulk delete | N/A routing |
| is_port_patch_panel | `search` | Search | N/A routing |
| is_port_patch_panel | `sort` | Sort links | N/A routing |
| is_port_patch_panel | `create` | Create form | N/A routing |
| is_port_patch_panel | `view` | View record | N/A routing |
| is_port_patch_panel | `edit` | Edit form | N/A routing |
| is_port_patch_panel | `list_all` | List all | N/A routing |
| is_port_patch_panel | `export_pdf` | Export PDF | N/A routing |
| is_port_patch_panel | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_port_patch_panel | `import_db` | Import Excel | N/A routing |
| is_port_patch_panel | `single_delete` | Single delete | N/A routing |
| is_port_patch_panel | `clear_table` | Clear table | N/A routing |
| is_cctv | `clear` | Tenant clear | N/A routing |
| is_cctv | `sample_data` | Sample data | N/A routing |
| is_cctv | `add` | Bulk random rows | N/A routing |
| is_cctv | `pagination` | Pagination | N/A routing |
| is_cctv | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_cctv | `bulk_delete` | Bulk delete | N/A routing |
| is_cctv | `search` | Search | N/A routing |
| is_cctv | `sort` | Sort links | N/A routing |
| is_cctv | `create` | Create form | N/A routing |
| is_cctv | `view` | View record | N/A routing |
| is_cctv | `edit` | Edit form | N/A routing |
| is_cctv | `list_all` | List all | N/A routing |
| is_cctv | `export_pdf` | Export PDF | N/A routing |
| is_cctv | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_cctv | `import_db` | Import Excel | N/A routing |
| is_cctv | `single_delete` | Single delete | N/A routing |
| is_cctv | `clear_table` | Clear table | N/A routing |
| is_phone | `clear` | Tenant clear | N/A routing |
| is_phone | `sample_data` | Sample data | N/A routing |
| is_phone | `add` | Bulk random rows | N/A routing |
| is_phone | `pagination` | Pagination | N/A routing |
| is_phone | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_phone | `bulk_delete` | Bulk delete | N/A routing |
| is_phone | `search` | Search | N/A routing |
| is_phone | `sort` | Sort links | N/A routing |
| is_phone | `create` | Create form | N/A routing |
| is_phone | `view` | View record | N/A routing |
| is_phone | `edit` | Edit form | N/A routing |
| is_phone | `list_all` | List all | N/A routing |
| is_phone | `export_pdf` | Export PDF | N/A routing |
| is_phone | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_phone | `import_db` | Import Excel | N/A routing |
| is_phone | `single_delete` | Single delete | N/A routing |
| is_phone | `clear_table` | Clear table | N/A routing |
| is_firewall | `clear` | Tenant clear | N/A routing |
| is_firewall | `sample_data` | Sample data | N/A routing |
| is_firewall | `add` | Bulk random rows | N/A routing |
| is_firewall | `pagination` | Pagination | N/A routing |
| is_firewall | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_firewall | `bulk_delete` | Bulk delete | N/A routing |
| is_firewall | `search` | Search | N/A routing |
| is_firewall | `sort` | Sort links | N/A routing |
| is_firewall | `create` | Create form | N/A routing |
| is_firewall | `view` | View record | N/A routing |
| is_firewall | `edit` | Edit form | N/A routing |
| is_firewall | `list_all` | List all | N/A routing |
| is_firewall | `export_pdf` | Export PDF | N/A routing |
| is_firewall | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_firewall | `import_db` | Import Excel | N/A routing |
| is_firewall | `single_delete` | Single delete | N/A routing |
| is_firewall | `clear_table` | Clear table | N/A routing |
| is_access_point | `clear` | Tenant clear | N/A routing |
| is_access_point | `sample_data` | Sample data | N/A routing |
| is_access_point | `add` | Bulk random rows | N/A routing |
| is_access_point | `pagination` | Pagination | N/A routing |
| is_access_point | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_access_point | `bulk_delete` | Bulk delete | N/A routing |
| is_access_point | `search` | Search | N/A routing |
| is_access_point | `sort` | Sort links | N/A routing |
| is_access_point | `create` | Create form | N/A routing |
| is_access_point | `view` | View record | N/A routing |
| is_access_point | `edit` | Edit form | N/A routing |
| is_access_point | `list_all` | List all | N/A routing |
| is_access_point | `export_pdf` | Export PDF | N/A routing |
| is_access_point | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_access_point | `import_db` | Import Excel | N/A routing |
| is_access_point | `single_delete` | Single delete | N/A routing |
| is_access_point | `clear_table` | Clear table | N/A routing |
| is_other | `clear` | Tenant clear | N/A routing |
| is_other | `sample_data` | Sample data | N/A routing |
| is_other | `add` | Bulk random rows | N/A routing |
| is_other | `pagination` | Pagination | N/A routing |
| is_other | `bulk_cancel` | Bulk Cancel UI | N/A routing |
| is_other | `bulk_delete` | Bulk delete | N/A routing |
| is_other | `search` | Search | N/A routing |
| is_other | `sort` | Sort links | N/A routing |
| is_other | `create` | Create form | N/A routing |
| is_other | `view` | View record | N/A routing |
| is_other | `edit` | Edit form | N/A routing |
| is_other | `list_all` | List all | N/A routing |
| is_other | `export_pdf` | Export PDF | N/A routing |
| is_other | `export_xlsx` | Export Excel (.xlsx) | N/A routing |
| is_other | `import_db` | Import Excel | N/A routing |
| is_other | `single_delete` | Single delete | N/A routing |
| is_other | `clear_table` | Clear table | N/A routing |

### Failure summary (by step)

Counts are across all modules and companies in this JSON. **Typical cause** is the usual reason; **This run** is taken from the first matching failure note when available.

| Step | Label | Failures | Typical cause | This run |
|---|---|---:|---|---|
| `add` | Bulk random rows | 1 | Runner could not insert enough random rows (unique keys, missing FK parents, or column out of range) | This run: Could not insert random rows; total=24 target=30; Cannot add or update a child row: a foreign key... |

## Preflight (company switch)

Verifies the runner can switch session scope to each company before module tests.

| Company ID | Company name | Result | Notes |
|---:|---|---|---|
| 1 | TechCorp Global | OK | Session switched to this company |

## Results by module (Pass and Fail)

### switch_ports — company 1 (TechCorp Global)

| Step | Label | Result | Notes |
|---|---|---|---|
| `mysql` | database.sql seed rows | OK | database.sql: 120 row(s) |
| `error_log` | Error log | OK | no error_log.txt |
| `list` | List page | OK | HTTP 200, no fatal; bulk UI visible (2 rows >= perPage 2); pagination hidden (2 rows <= perPage 2) |
| `ui_check` | Table Actions UI | OK | Actions mapped (itm-actions-cell + data-itm-actions-origin on header and body) |
| `clear` | Tenant clear | OK | SQL tenant clear |
| `sample_data` | Sample data | OK | HTTP sample seed; table rows in HTML |
| `add` | Bulk random rows | Failed | Could not insert random rows; total=24 target=30; Cannot add or update a child row: a foreign key constraint fails (`itmanagement`.`switch_ports`, CONSTRAINT... |
| `pagination` | Pagination | OK | page=1 Next→2, page=2 Previous→1 in HTML (sort=id, dir=DESC) |
| `bulk_cancel` | Bulk Cancel UI | OK | shared JS: Cancel label + exitSelectionMode + data-itm-bulk-cancel; bulk-delete-form in HTML; bulk-delete-selection.js in HTML; bulk_action + Select to Delet... |
| `bulk_delete` | Bulk delete | OK | deleted ids=195,194; bulk UI + rows in HTML |
| `search` | Search | OK | HTTP 200; search input + table in HTML |
| `sort` | Sort links | OK | sort=port_type in HTML |
| `create` | Create form | OK | create form in HTML |
| `view` | View record | OK | view screen in HTML (id=195) |
| `edit` | Edit form | OK | edit form in HTML (id=195) |
| `list_all` | List all | OK | list table in HTML |
| `export_pdf` | Export PDF | OK | Export PDF in HTML, 2 row(s) |
| `export_xlsx` | Export Excel (.xlsx) | OK | Export Excel (.xlsx) in HTML, 2 row(s) |
| `clear_table` | Clear table | OK | table empty for tenant; Clear Table in HTML |
| `clear` | Tenant clear | OK | SQL tenant clear |
| `import_db` | Import Excel | OK | data-itm-db-import-endpoint + table-tools in HTML; imported Export Excel headers with insertable row (database.sql FK ids when needed); inserted=1; 1 row(s) ... |
| `single_delete` | Single delete | OK | deleted id=232; delete control in HTML |
| `sample_data` | Sample data | OK | HTTP sample seed; table rows in HTML; table rows in HTML |
| `error_log` | Error log | OK | 0 errors |

## Failures only (quick index)

| Module | Co | Step | Label | Notes |
|---|---|---|---|---|
| switch_ports | 1 | `add` | Bulk random rows | Could not insert random rows; total=24 target=30; Cannot add or update a child row: a foreign key constraint fails (`itmanagement`.`switch_ports`, CONSTRAINT... |

## Skip (quick index)

_No skipped steps recorded (notes starting with Skip or N/A)._
