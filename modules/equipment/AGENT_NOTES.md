# AGENT_NOTES.md - Equipment

## 1. Module Purpose
Manages IT assets (Equipment), including servers, workstations, switches, and peripherals. Includes tracking of specifications and assignments.

## 2. Key Tables
- **equipment** — main asset records.
- **employee_assignment_history** — one row per `(company_id, employee_id)`; synced from equipment assignee changes. Row shape, UPSERT rules, and manual CRUD pitfalls: **`modules/employee_assignment_history/AGENT_NOTES.md`**.

## 3. Required Relationships
- **equipment** → depends on **companies**.
- **equipment** → depends on **equipment_types**.
- **equipment** → depends on **equipment_statuses**.
- **equipment** → depends on **manufacturers**.
- **equipment** → optional **departments** (`department_id`, `DEFAULT NULL`, `ON DELETE SET NULL`).
- **equipment** → optional **suppliers** (`supplier_id`, `DEFAULT NULL`, `ON DELETE SET NULL`).
- **equipment** → optional **employees** (`assigned_to_employee_id`, `DEFAULT NULL`, `ON DELETE SET NULL`).

## 4. Business Rules (Critical for Agents)
- **Department:** Optional FK to `departments.id`, tenant-scoped on save and joins (`company_id`). Create/edit use the shared quick-add select (`data-add-table="departments"`, label column `name`); `select_options_api.php` auto-fills `company_id` and `active`. Quick-add requires **name** only; **code** is optional via `data-add-extra-fields`. Dropdown labels use `itm_department_option_label()` (`name (code)`). List shows `departments.code` with fallback to `departments.name`; view shows `departments.name`.
- **Supplier:** Optional FK to `suppliers.id`, tenant-scoped on save and joins. Create/edit row with Serial Number / Model (third column); quick-add select like Department. Quick-add requires **name** and **status_id** (`supplier_statuses`); **supplier_code** optional. Dropdown labels use `itm_supplier_option_label()` (`name (supplier_code)`). View shows `suppliers.name`. **Not on index.php** list.
- **Location / Rack:** Optional FKs on create/edit. Dropdown labels use `itm_location_option_label()` (`name (location_code)`) and `itm_rack_option_label()` (`name (rack_code)`). Index search matches location/rack names and codes via `includes/itm_equipment_search.php`.
- **Assign To Employee:** Optional FK to `employees.id` via `assigned_to_employee_id`. Create/edit **Assign To Employee** select (no ➕ quick-add on employees). Hidden fields on the same row: `equipment_id` (edit record id, `0` on create) and `assigned_date` (date from equipment `updated_at`/`created_at`). Labels use `itm_employee_manager_option_label()` (display name + username). View shows `assigned_employee_label`. **Not on index.php** list; index search matches assignee names and `username`.
- **Assignment history sync (`equipment_assignment_sync.php`):** After equipment INSERT/UPDATE (inside the existing transaction in `create.php`), `equipment_sync_assigned_employee()` updates `equipment.assigned_to_employee_id` and upserts `employee_assignment_history` (replace policy: assigning an employee clears any other equipment they held). `assigned_date` is taken from the saved equipment row `DATE(updated_at)` after the equipment write; the create/edit form posts the same value as hidden `assigned_date` (derived from `updated_at` on load, refreshed from DB at sync time). Unassign sets assignee NULL and closes open history for that `equipment_id` (`returned_date = CURDATE()`). Delete calls `equipment_close_assignment_history_on_delete()` before soft-deleting the equipment row (FK `ON DELETE SET NULL` on history `equipment_id`).
- **Create/edit layout:** Row 1 ends with MAC Address (swapped with Manufacturer); row 2 ends with Manufacturer. Serial / Model / Supplier share one `form-row-3`. Purchase Date / Cost row has no Department. **Assign To Employee** (hidden `equipment_id` + `assigned_date`) and **Department** share a `form-row-3` immediately before Notes.
- **IDF synchronization:** Create/Edit/Update/Delete/Copy/Move must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` aligned — transactions required; run `php scripts/idfs_sync_human_test.php` after changes.
- **Asset Tagging:** Each item should ideally have a unique serial or asset number within the company.
- **Type-Specific Logic:** `modules/is_*` façades delegate here; do not delete canonical `is_switch`, `is_server`, etc. Gate-excluded UI configuration list-contract gaps for façades are reviewed under registry key `is_*` in `scripts/data/ui_configuration_reviewed.json` (no local `index.php` table — list chrome is here).
- **Switch port tiles:** RJ45/SFP icon mapping per AGENTS.md (Unknown vs active PNG paths).
- **Add sample data:** `index.php` POST `add_sample_data` calls `itm_seed_insert_equipment_sample_rows()` (via `itm_seed_table_from_database_sql('equipment')`) — ensures **Primary File Server** (Server type) and **Core Switch** (`equipment_rj45` **24 ports**) plus **24 RJ45** `switch_ports` rows; bypasses `02_data_sample.sql` FK remap on empty tenants.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view lists all six scaffold audit columns (`deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) with employee names and `d-m-Y - H:i:s` timestamps; list hides meta fields. Employment/equipment/ticket **status** badges are separate from row `active` (soft-delete mirror).
- **Standard CRUD**.
- **Photo Upload**: Supports uploading photos of the equipment.
- **Search & Filter**: Index list search uses `includes/itm_equipment_search.php` (`itm_equipment_search_join_sql()`, `itm_equipment_build_search_where_sql()`) to match scalar fields (including dates, purchase cost, workstation/switch/printer columns, `photo_filename`) plus all create/edit FK labels (department code, supplier code, location code, rack code, assignee username, type/manufacturer/status/warranty/workstation/switch lookup names, etc.). Supplier and assignee columns are not shown on the index table. Search reset uses emoji-only 🔙 on `<a>` (`title="Clear"`). Regression: `php scripts/verify_employees_equipment_search_coverage.php`.
- **List header:** `data-itm-new-button-managed` row with centered `sanitize($moduleListHeading)` from `itm_sidebar_label_for_module()`; Settings `new_button_position` gates left/right ➕ create slots.
- **Bulk toolbar gate:** index sets `$showBulkActions = ($totalRows >= $perPage)` after the tenant count. Bulk card, row `ids[]` checkboxes, and header select-all render only when `$showBulkActions`; includes `js/bulk-delete-selection.js` in `index.php` HTML (plus header global load) with Cancel via `data-itm-bulk-cancel="1"`.
- **Active / soft-delete:** Row `active` is a soft-delete mirror (create/edit hidden `active=1`; soft-delete sets `active=0` with `deleted_by` / `deleted_at`). Business Active/Inactive stays on `status_id` → `equipment_statuses` and is shown on **list/view as status badges** — row `active` is **not** shown. List filters `deleted_at IS NULL` and hides the six audit meta fields; view shows audit meta. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure + `delete_functions.php`, `equipment_assignment_sync.php`.
- **edit.php** delegates to **create.php** — keep department, supplier, and assignee field logic in `create.php` only.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.
- Employee assignee POST values must belong to the active company (`employees.company_id`). Do not filter assignee options on `employees.active` — login eligibility uses `employment_status_id` / `employee_statuses`; the assignee dropdown lists all tenant employees (see `equipment_fetch_employee_options()`).
- **Active / soft-delete:** Same as section 5 — row `active` mirrors soft-delete; business state is `status_id` → `equipment_statuses`.

## 9. Audit Logging Requirements
- Managed via database triggers (`department_id`, `supplier_id`, `assigned_to_employee_id` in equipment audit JSON payloads).

## 10. Common Pitfalls
- **Deleting with Relations**: Deleting equipment may fail if it has active switch port assignments or is linked to tickets. [Cursor-Valid]
- **Supplier quick-add:** `suppliers.status_id` is NOT NULL — the equipment form passes `status_id` via `data-add-extra-fields`. [Cursor-Valid]
- **Assignment replace policy:** One employee history row per company; assigning equipment B to an employee clears assignee on any other equipment that employee held (see **`modules/employee_assignment_history/AGENT_NOTES.md`**). [Cursor-Valid]
- **Assign To Employee dropdown empty:** do not filter `employees` on row `active` for login eligibility — scope by `company_id` only in `equipment_fetch_employee_options()`; employment status is separate from soft-delete `active`. [Cursor-Fixed]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM equipment WHERE company_id = ? AND asset_number = ?");
$stmt->bind_param("is", $companyId, $assetNumber);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO equipment (company_id, equipment_type_id, hostname, department_id, supplier_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisii", $companyId, $typeId, $hostname, $departmentId, $supplierId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary inventory module.
