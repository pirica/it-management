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
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Department:** Optional FK to `departments.id`, tenant-scoped on save and joins (`company_id`). Create/edit use the shared quick-add select (`data-add-table="departments"`, label column `name`); `select_options_api.php` auto-fills `company_id` and `active`. Quick-add requires **name** only; **code** is optional via `data-add-extra-fields`. List shows `departments.code` with fallback to `departments.name`; view shows `departments.name`.
- **Supplier:** Optional FK to `suppliers.id`, tenant-scoped on save and joins. Create/edit row with Serial Number / Model (third column); quick-add select like Department. Quick-add requires **name** and **status_id** (`supplier_statuses`); **supplier_code** optional. View shows `suppliers.name`. **Not on index.php** list.
- **Assign To Employee:** Optional FK to `employees.id` via `assigned_to_employee_id`. Create/edit **Assign To Employee** select (no ➕ — employees module is Protection Zone). Hidden fields on the same row: `equipment_id` (edit record id, `0` on create) and `assigned_date` (date from equipment `updated_at`/`created_at`). Labels use `first_name` + `last_name`, fallback `display_name`. View shows `assigned_employee_label`. **Not on index.php** list; index search matches assignee `first_name`, `last_name`, and `display_name`.
- **Assignment history sync (`equipment_assignment_sync.php`):** After equipment INSERT/UPDATE (inside the existing transaction in `create.php`), `equipment_sync_assigned_employee()` updates `equipment.assigned_to_employee_id` and upserts `employee_assignment_history` (replace policy: assigning an employee clears any other equipment they held). `assigned_date` is taken from the saved equipment row `DATE(updated_at)` after the equipment write; the create/edit form posts the same value as hidden `assigned_date` (derived from `updated_at` on load, refreshed from DB at sync time). Unassign sets assignee NULL and closes open history for that `equipment_id` (`returned_date = CURDATE()`). Delete calls `equipment_close_assignment_history_on_delete()` before `DELETE` (FK `ON DELETE SET NULL` on history `equipment_id`).
- **Create/edit layout:** Row 1 ends with MAC Address (swapped with Manufacturer); row 2 ends with Manufacturer. Serial / Model / Supplier share one `form-row-3`. Purchase Date / Cost row has no Department. **Assign To Employee** (hidden `equipment_id` + `assigned_date`) and **Department** share a `form-row-3` immediately before Notes.
- **IDF synchronization:** Create/Edit/Update/Delete/Copy/Move must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` aligned — transactions required; run `php scripts/idfs_sync_human_test.php` after changes.
- **Asset Tagging:** Each item should ideally have a unique serial or asset number within the company.
- **Type-Specific Logic:** `modules/is_*` façades delegate here; do not delete canonical `is_switch`, `is_server`, etc.
- **Switch port tiles:** RJ45/SFP icon mapping per AGENTS.md (Unknown vs active PNG paths).

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Photo Upload**: Supports uploading photos of the equipment.
- **Search & Filter**: Extensive filtering by type, status, assignment, department code/name, supplier name/code, and assignee name fields on the index list (supplier and assignee columns not shown on index).

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure + `delete_functions.php`, `equipment_assignment_sync.php`.
- **edit.php** delegates to **create.php** — keep department, supplier, and assignee field logic in `create.php` only.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.
- Employee assignee POST values must belong to the active company (`employees.company_id` + `active = 1`).

## 9. Audit Logging Requirements
- Managed via database triggers (`department_id`, `supplier_id`, `assigned_to_employee_id` in equipment audit JSON payloads).

## 10. Common Pitfalls
- **Deleting with Relations**: Deleting equipment may fail if it has active switch port assignments or is linked to tickets.
- **Supplier quick-add:** `suppliers.status_id` is NOT NULL — the equipment form passes `status_id` via `data-add-extra-fields`.
- **Assignment replace policy:** One employee history row per company; assigning equipment B to an employee clears assignee on any other equipment that employee held (see **`modules/employee_assignment_history/AGENT_NOTES.md`**).
- **Do not edit `modules/employees/`** from equipment — load dropdown options read-only.

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
