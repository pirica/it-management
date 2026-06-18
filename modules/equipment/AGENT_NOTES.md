# AGENT_NOTES.md - Equipment

## 1. Module Purpose
Manages IT assets (Equipment), including servers, workstations, switches, and peripherals. Includes tracking of specifications and assignments.

## 2. Key Tables
- **equipment** — main asset records.

## 3. Required Relationships
- **equipment** → depends on **companies**.
- **equipment** → depends on **equipment_types**.
- **equipment** → depends on **equipment_statuses**.
- **equipment** → depends on **manufacturers**.
- **equipment** → optional **departments** (`department_id`, `DEFAULT NULL`, `ON DELETE SET NULL`).
- **equipment** → optional **suppliers** (`supplier_id`, `DEFAULT NULL`, `ON DELETE SET NULL`).
- **equipment** → links to **employees** (via `assigned_to_employee_id`).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Department:** Optional FK to `departments.id`, tenant-scoped on save and joins (`company_id`). Create/edit use the shared quick-add select (`data-add-table="departments"`, label column `name`); `select_options_api.php` auto-fills `company_id` and `active`. Quick-add requires **name** only; **code** is optional via `data-add-extra-fields`. List shows `departments.code` with fallback to `departments.name`; view shows `departments.name`. **Not on index** for supplier; department remains on index list.
- **Supplier:** Optional FK to `suppliers.id`, tenant-scoped on save and joins. Create/edit row with Serial Number / Model (third column); quick-add select like Department. Quick-add requires **name** and **status_id** (`supplier_statuses`); **supplier_code** optional. View shows `suppliers.name`. **Not on index.php** list.
- **Create/edit layout:** Row 1 ends with MAC Address (swapped with Manufacturer); row 2 ends with Manufacturer. Serial / Model / Supplier share one `form-row-3`.
- **IDF synchronization:** Create/Edit/Update/Delete/Copy/Move must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` aligned — transactions required; run `php scripts/idfs_sync_human_test.php` after changes.
- **Asset Tagging:** Each item should ideally have a unique serial or asset number within the company.
- **Type-Specific Logic:** `modules/is_*` façades delegate here; do not delete canonical `is_switch`, `is_server`, etc.
- **Switch port tiles:** RJ45/SFP icon mapping per AGENTS.md (Unknown vs active PNG paths).

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Photo Upload**: Supports uploading photos of the equipment.
- **Search & Filter**: Extensive filtering by type, status, assignment, department code/name, and supplier name/code on the index list (supplier column not shown on index).

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure + `delete_functions.php`.
- **edit.php** delegates to **create.php** — keep department and supplier field logic in `create.php` only.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers (`department_id`, `supplier_id` in equipment audit JSON payloads).

## 10. Common Pitfalls
- **Deleting with Relations**: Deleting equipment may fail if it has active switch port assignments or is linked to tickets.
- **Supplier quick-add:** `suppliers.status_id` is NOT NULL — the equipment form passes `status_id` via `data-add-extra-fields`.

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
