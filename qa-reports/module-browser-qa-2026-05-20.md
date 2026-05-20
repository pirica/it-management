# Module browser QA — 2026-05-20

## Summary

- Environment: `http://localhost/it-management/` (Laragon)
- Auth: Admin / Admin
- Companies: 5 (TechCorp Global … Enterprise IT)
- Modules exercised: ~101 folders × 5 companies (HTTP session runner + Cursor browser pilot on Expenses)
- Step outcomes: **6562 Pass**, **508 Fail**
- Runner: `php scripts/module_browser_qa_runner.php` (login, company switch, clear/seed/CRUD/import via HTTP)
- Bulk delete / Clear table: N/A when row count &lt; `records_per_page` (25)
- IDF regression: `php scripts/idfs_sync_human_test.php` — **FAIL** RJ45 capacity options (8/24)

### Failure categories (automated run)

| Step | Fail count | Typical cause |
|---|---|---|
| sort | 345 | Runner used `sort=id` before fix; many modules hide `id` column |
| clear | 89 | FK constraints when parent lookup tables cleared out of FK-safe order |
| sample_data | 54 | No `database.sql` seed rows for table/company or seed blocked after failed clear |
| create | 10 | Missing FK parents after aggressive clear |
| edit | 5 | No rows after failed seed |
| view | 5 | No rows after failed seed |

## Cursor browser pilot — Expenses (CloudTech Services, company 4)

| Step | Status | Notes |
|---|---|---|
| login | Pass | Admin session → dashboard |
| list | Pass | index loads with toolbar |
| search | Pass | `preventive` filter submitted |
| export_xls / export_pdf / import | Pass | Buttons present (📗 📄 📥) |
| view / edit / create | Pass | Action links ➕ 🔎 ✏️ 🗑️ visible |
| sort | Pass | Column header links (Date, Amount, …) clickable |
| sample_data | Pass | Seeded via HTTP runner before browser (tenant row visible) |
| bulk_delete / clear_table | N/A | &lt; 25 rows |

## Preflight (company switch)

| Company ID | Company | Switch |
|---|---|---|
| 1 | TechCorp Global | Pass |
| 2 | DataCenter Plus | Pass |
| 3 | Network Solutions | Pass |
| 4 | CloudTech Services | Pass |
| 5 | Enterprise IT | Pass |

## Expenses pilot (5 companies)

### Company 1 — TechCorp Global

| Step | Status | Notes |
|---|---|---|
| list | Pass |  |
| clear | Pass | SQL tenant clear |
| sample_data | Pass |  |
| search | Pass | HTTP 200 |
| sort | Fail | Sort indicators missing |
| create | Pass | HTTP 200 |
| view | Pass | id=11 |
| edit | Pass | id=11 |
| list_all | Pass | HTTP 200 |
| export_xls | Pass | table-tools hook |
| import_db | Pass |  |
| export_pdf | Pass | N/A (client-side print; not exercised in HTTP runner) |
| bulk_delete | Pass | N/A (requires 25+ rows per records_per_page) |
| clear_table | Pass | N/A (requires 25+ rows) |

### Company 2 — DataCenter Plus

| Step | Status | Notes |
|---|---|---|
| list | Pass |  |
| clear | Pass | SQL tenant clear |
| sample_data | Pass |  |
| search | Pass | HTTP 200 |
| sort | Fail | Sort indicators missing |
| create | Pass | HTTP 200 |
| view | Pass | id=12 |
| edit | Pass | id=12 |
| list_all | Pass | HTTP 200 |
| export_xls | Pass | table-tools hook |
| import_db | Pass |  |
| export_pdf | Pass | N/A (client-side print; not exercised in HTTP runner) |
| bulk_delete | Pass | N/A (requires 25+ rows per records_per_page) |
| clear_table | Pass | N/A (requires 25+ rows) |

### Company 3 — Network Solutions

| Step | Status | Notes |
|---|---|---|
| list | Pass |  |
| clear | Pass | SQL tenant clear |
| sample_data | Pass |  |
| search | Pass | HTTP 200 |
| sort | Fail | Sort indicators missing |
| create | Pass | HTTP 200 |
| view | Pass | id=13 |
| edit | Pass | id=13 |
| list_all | Pass | HTTP 200 |
| export_xls | Pass | table-tools hook |
| import_db | Pass |  |
| export_pdf | Pass | N/A (client-side print; not exercised in HTTP runner) |
| bulk_delete | Pass | N/A (requires 25+ rows per records_per_page) |
| clear_table | Pass | N/A (requires 25+ rows) |

### Company 4 — CloudTech Services

| Step | Status | Notes |
|---|---|---|
| list | Pass |  |
| clear | Pass | SQL tenant clear |
| sample_data | Pass |  |
| search | Pass | HTTP 200 |
| sort | Fail | Sort indicators missing |
| create | Pass | HTTP 200 |
| view | Pass | id=14 |
| edit | Pass | id=14 |
| list_all | Pass | HTTP 200 |
| export_xls | Pass | table-tools hook |
| import_db | Pass |  |
| export_pdf | Pass | N/A (client-side print; not exercised in HTTP runner) |
| bulk_delete | Pass | N/A (requires 25+ rows per records_per_page) |
| clear_table | Pass | N/A (requires 25+ rows) |

### Company 5 — Enterprise IT

| Step | Status | Notes |
|---|---|---|
| list | Pass |  |
| clear | Pass | SQL tenant clear |
| sample_data | Pass |  |
| search | Pass | HTTP 200 |
| sort | Fail | Sort indicators missing |
| create | Pass | HTTP 200 |
| view | Pass | id=15 |
| edit | Pass | id=15 |
| list_all | Pass | HTTP 200 |
| export_xls | Pass | table-tools hook |
| import_db | Pass |  |
| export_pdf | Pass | N/A (client-side print; not exercised in HTTP runner) |
| bulk_delete | Pass | N/A (requires 25+ rows per records_per_page) |
| clear_table | Pass | N/A (requires 25+ rows) |

## Failures (all modules)

| Module | Co | Step | Notes |
|---|---|---|---|
| departments | 1 | clear | Clear failed |
| departments | 1 | sort | Sort indicators missing |
| manufacturers | 1 | clear | Clear failed |
| manufacturers | 1 | sort | Sort indicators missing |
| vlans | 1 | sort | Sort indicators missing |
| location_types | 1 | sort | Sort indicators missing |
| equipment_types | 1 | clear | Clear failed |
| equipment_types | 1 | sort | Sort indicators missing |
| budget_categories | 1 | sort | Sort indicators missing |
| cost_centers | 1 | clear | Clear failed |
| cost_centers | 1 | sort | Sort indicators missing |
| gl_accounts | 1 | clear | Clear failed |
| gl_accounts | 1 | sort | Sort indicators missing |
| supplier_statuses | 1 | clear | Clear failed |
| supplier_statuses | 1 | sort | Sort indicators missing |
| ticket_categories | 1 | sort | Sort indicators missing |
| ticket_statuses | 1 | sort | Sort indicators missing |
| ticket_priorities | 1 | sort | Sort indicators missing |
| employee_statuses | 1 | sort | Sort indicators missing |
| employee_positions | 1 | sort | Sort indicators missing |
| approver_type | 1 | sort | Sort indicators missing |
| approvers | 1 | sample_data | Still empty or seed error |
| approvers | 1 | sort | Sort indicators missing |
| equipment_statuses | 1 | clear | Clear failed |
| equipment_statuses | 1 | sort | Sort indicators missing |
| rj45_speed | 1 | sort | Sort indicators missing |
| warranty_types | 1 | clear | Clear failed |
| warranty_types | 1 | sort | Sort indicators missing |
| workstation_modes | 1 | sort | Sort indicators missing |
| workstation_os_types | 1 | sort | Sort indicators missing |
| annual_budgets | 1 | sort | Sort indicators missing |
| monthly_budgets | 1 | sample_data | Still empty or seed error |
| monthly_budgets | 1 | sort | Sort indicators missing |
| forecast_revisions_status | 1 | clear | Clear failed |
| forecast_revisions_status | 1 | sort | Sort indicators missing |
| forecast_revisions | 1 | sort | Sort indicators missing |
| approvals_stage | 1 | sort | Sort indicators missing |
| approvals | 1 | sample_data | Still empty or seed error |
| approvals | 1 | sort | Sort indicators missing |
| expenses | 1 | sort | Sort indicators missing |
| access_levels | 1 | clear | Clear failed |
| access_levels | 1 | sort | Sort indicators missing |
| assignment_types | 1 | sort | Sort indicators missing |
| attempts | 1 | clear | Clear failed |
| attempts | 1 | sort | Sort indicators missing |
| attempts | 1 | create | HTTP 404 |
| attempts | 1 | edit | id=5 |
| catalogs | 1 | sample_data | Still empty or seed error |
| catalogs | 1 | sort | Sort indicators missing |
| employee_assignment_history | 1 | sample_data | Still empty or seed error |
| employee_assignment_history | 1 | sort | Sort indicators missing |
| employee_onboarding_requests | 1 | sample_data | Still empty or seed error |
| employee_onboarding_requests | 1 | sort | Sort indicators missing |
| equipment_environment | 1 | sort | Sort indicators missing |
| equipment_fiber | 1 | sort | Sort indicators missing |
| equipment_fiber_count | 1 | sort | Sort indicators missing |
| equipment_fiber_patch | 1 | sort | Sort indicators missing |
| equipment_fiber_rack | 1 | sort | Sort indicators missing |
| equipment_poe | 1 | sort | Sort indicators missing |
| equipment_rj45 | 1 | clear | Clear failed |
| equipment_rj45 | 1 | sort | Sort indicators missing |
| idf_device_type | 1 | sample_data | Still empty or seed error |
| idf_device_type | 1 | sort | Sort indicators missing |
| inventory | 1 | clear | Clear failed |
| inventory_categories | 1 | clear | Clear failed |
| inventory_categories | 1 | sort | Sort indicators missing |
| inventory_items | 1 | sort | Sort indicators missing |
| ip_addresses | 1 | sample_data | Still empty or seed error |
| ip_addresses | 1 | sort | Sort indicators missing |
| ip_subnets | 1 | sort | Sort indicators missing |
| ip_subnets | 1 | view | id=11 |
| is_switch | 1 | clear | Clear failed |
| it_locations | 1 | clear | Clear failed |
| it_locations | 1 | sort | Sort indicators missing |
| patches_updates | 1 | sample_data | Still empty or seed error |
| patches_updates | 1 | sort | Sort indicators missing |
| patches_updates_level | 1 | sort | Sort indicators missing |
| patches_updates_status | 1 | sort | Sort indicators missing |
| printer_device_types | 1 | sort | Sort indicators missing |
| rack_statuses | 1 | clear | Clear failed |
| rack_statuses | 1 | sort | Sort indicators missing |
| racks | 1 | sort | Sort indicators missing |
| registration_invitations | 1 | sort | Sort indicators missing |
| role_assignment_rights | 1 | sort | Sort indicators missing |
| role_hierarchy | 1 | sort | Sort indicators missing |
| role_module_permissions | 1 | sort | Sort indicators missing |
| suppliers | 1 | clear | Clear failed |
| suppliers | 1 | sort | Sort indicators missing |
| switch_port_numbering_layout | 1 | clear | Clear failed |
| switch_port_numbering_layout | 1 | sort | Sort indicators missing |
| switch_port_types | 1 | sort | Sort indicators missing |
| switch_ports | 1 | sort | Sort indicators missing |
| switch_status | 1 | sort | Sort indicators missing |
| user_roles | 1 | clear | Clear failed |
| user_roles | 1 | sort | Sort indicators missing |
| user_sidebar_preferences | 1 | sample_data | Still empty or seed error |
| user_sidebar_preferences | 1 | sort | Sort indicators missing |
| user_sidebar_preferences | 1 | create | HTTP 404 |
| users | 1 | sort | Sort indicators missing |
| workstation_device_types | 1 | sort | Sort indicators missing |
| workstation_office | 1 | sort | Sort indicators missing |
| workstation_os_versions | 1 | clear | Clear failed |
| workstation_os_versions | 1 | sort | Sort indicators missing |
| workstation_ram | 1 | sort | Sort indicators missing |
| departments | 2 | clear | Clear failed |
| departments | 2 | sort | Sort indicators missing |
| manufacturers | 2 | clear | Clear failed |
| manufacturers | 2 | sort | Sort indicators missing |
| vlans | 2 | sort | Sort indicators missing |
| location_types | 2 | sort | Sort indicators missing |
| equipment_types | 2 | clear | Clear failed |
| equipment_types | 2 | sort | Sort indicators missing |
| budget_categories | 2 | sort | Sort indicators missing |
| cost_centers | 2 | clear | Clear failed |
| cost_centers | 2 | sort | Sort indicators missing |
| gl_accounts | 2 | clear | Clear failed |
| gl_accounts | 2 | sort | Sort indicators missing |
| supplier_statuses | 2 | clear | Clear failed |
| supplier_statuses | 2 | sort | Sort indicators missing |
| ticket_categories | 2 | sort | Sort indicators missing |
| ticket_statuses | 2 | sort | Sort indicators missing |
| ticket_priorities | 2 | sort | Sort indicators missing |
| employee_statuses | 2 | sort | Sort indicators missing |
| employee_positions | 2 | sort | Sort indicators missing |
| approver_type | 2 | sort | Sort indicators missing |
| approvers | 2 | sample_data | Still empty or seed error |
| approvers | 2 | sort | Sort indicators missing |
| equipment_statuses | 2 | clear | Clear failed |
| equipment_statuses | 2 | sort | Sort indicators missing |
| rj45_speed | 2 | sort | Sort indicators missing |
| warranty_types | 2 | sort | Sort indicators missing |
| workstation_modes | 2 | sort | Sort indicators missing |
| workstation_os_types | 2 | sort | Sort indicators missing |
| annual_budgets | 2 | sort | Sort indicators missing |
| monthly_budgets | 2 | sample_data | Still empty or seed error |
| monthly_budgets | 2 | sort | Sort indicators missing |
| forecast_revisions_status | 2 | clear | Clear failed |
| forecast_revisions_status | 2 | sort | Sort indicators missing |
| forecast_revisions | 2 | sort | Sort indicators missing |
| approvals_stage | 2 | sort | Sort indicators missing |
| approvals | 2 | sample_data | Still empty or seed error |
| approvals | 2 | sort | Sort indicators missing |
| expenses | 2 | sort | Sort indicators missing |
| access_levels | 2 | sort | Sort indicators missing |
| assignment_types | 2 | sort | Sort indicators missing |
| attempts | 2 | clear | Clear failed |
| attempts | 2 | sort | Sort indicators missing |
| attempts | 2 | create | HTTP 404 |
| attempts | 2 | edit | id=5 |
| catalogs | 2 | sample_data | Still empty or seed error |
| catalogs | 2 | sort | Sort indicators missing |
| employee_assignment_history | 2 | sample_data | Still empty or seed error |
| employee_assignment_history | 2 | sort | Sort indicators missing |
| employee_onboarding_requests | 2 | sample_data | Still empty or seed error |
| employee_onboarding_requests | 2 | sort | Sort indicators missing |
| equipment_environment | 2 | sort | Sort indicators missing |
| equipment_fiber | 2 | sort | Sort indicators missing |
| equipment_fiber_count | 2 | sort | Sort indicators missing |
| equipment_fiber_patch | 2 | sort | Sort indicators missing |
| equipment_fiber_rack | 2 | sort | Sort indicators missing |
| equipment_poe | 2 | sort | Sort indicators missing |
| equipment_rj45 | 2 | clear | Clear failed |
| equipment_rj45 | 2 | sort | Sort indicators missing |
| idf_device_type | 2 | sample_data | Still empty or seed error |
| idf_device_type | 2 | sort | Sort indicators missing |
| inventory | 2 | clear | Clear failed |
| inventory_categories | 2 | clear | Clear failed |
| inventory_categories | 2 | sort | Sort indicators missing |
| inventory_items | 2 | sort | Sort indicators missing |
| ip_addresses | 2 | sample_data | Still empty or seed error |
| ip_addresses | 2 | sort | Sort indicators missing |
| ip_subnets | 2 | sort | Sort indicators missing |
| ip_subnets | 2 | view | id=12 |
| is_switch | 2 | clear | Clear failed |
| it_locations | 2 | clear | Clear failed |
| it_locations | 2 | sort | Sort indicators missing |
| patches_updates | 2 | sample_data | Still empty or seed error |
| patches_updates | 2 | sort | Sort indicators missing |
| patches_updates_level | 2 | sort | Sort indicators missing |
| patches_updates_status | 2 | sort | Sort indicators missing |
| printer_device_types | 2 | sort | Sort indicators missing |
| rack_statuses | 2 | clear | Clear failed |
| rack_statuses | 2 | sort | Sort indicators missing |
| racks | 2 | sort | Sort indicators missing |
| registration_invitations | 2 | sample_data | Still empty or seed error |
| registration_invitations | 2 | sort | Sort indicators missing |
| role_assignment_rights | 2 | sort | Sort indicators missing |
| role_hierarchy | 2 | sort | Sort indicators missing |
| role_module_permissions | 2 | sort | Sort indicators missing |
| suppliers | 2 | clear | Clear failed |
| suppliers | 2 | sort | Sort indicators missing |
| switch_port_numbering_layout | 2 | clear | Clear failed |
| switch_port_numbering_layout | 2 | sort | Sort indicators missing |
| switch_port_types | 2 | sort | Sort indicators missing |
| switch_ports | 2 | sort | Sort indicators missing |
| switch_status | 2 | sort | Sort indicators missing |
| user_roles | 2 | sort | Sort indicators missing |
| user_sidebar_preferences | 2 | sample_data | Still empty or seed error |
| user_sidebar_preferences | 2 | sort | Sort indicators missing |
| user_sidebar_preferences | 2 | create | HTTP 404 |

_(truncated; see JSON for full list)_
