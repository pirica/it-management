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
- **equipment** → links to **employees** (via `assigned_to_employee_id`).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Department:** Optional FK to `departments.id`, tenant-scoped on save and joins (`company_id`). Create/edit use the shared quick-add select (`data-add-table="departments"`, label column `name`); `select_options_api.php` auto-fills `company_id` and `active`. Quick-add requires **name** only; **code** is optional via `data-add-extra-fields`. Persisted department fallback in `equipment_append_persisted_department_option()` is **company-scoped only** (no id-only cross-tenant lookup). List shows `departments.code` with fallback to `departments.name`; view shows `departments.name`.
- **IDF synchronization:** Create/Edit/Update/Delete/Copy/Move must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` aligned — transactions required; run `php scripts/idfs_sync_human_test.php` after changes.
- **Asset Tagging:** Each item should ideally have a unique serial or asset number within the company.
- **Type-Specific Logic:** `modules/is_*` façades delegate here; do not delete canonical `is_switch`, `is_server`, etc.
- **Switch port tiles:** RJ45/SFP icon mapping per AGENTS.md (Unknown vs active PNG paths).

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Photo Upload**: Supports uploading photos of the equipment.
- **Search & Filter**: Extensive filtering by type, status, assignment, and department code/name on the index list.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.
- **Switch Port Manager (equipment index tiles):**
  - **`includes/get_ports.php`** (POST, JSON or form) — `switch_id`, `csrf_token`. Loads/seeds tenant-scoped `switch_ports` for the active switch and returns ports plus lookup metadata (statuses, colors, VLANs, IDF/rack options). Success: `{"success":true,"ports":[…],…}` via `itm_api_json_response()`. Documented in `scripts/api.php`.
  - **`includes/update_port.php`** (POST, JSON or form) — `id`, `switch_id`, `csrf_token`, port field updates. Updates `switch_ports` scoped by `company_id`; may sync linked `idf_ports` when management/To IDF fields change. Errors use HTTP 4xx/5xx with `{"success":false,"error":"…"}`. Shared helpers: `includes/switch_port_api_helpers.php`.

## 7. File Structure
- Standard CRUD structure + `delete_functions.php`.
- **edit.php** delegates to **create.php** — keep department field logic in `create.php` only.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Deleting with Relations**: Deleting equipment may fail if it has active switch port assignments or is linked to tickets.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM equipment WHERE company_id = ? AND asset_number = ?");
$stmt->bind_param("is", $companyId, $assetNumber);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO equipment (company_id, equipment_type_id, hostname, department_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iisi", $companyId, $typeId, $hostname, $departmentId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary inventory module.
