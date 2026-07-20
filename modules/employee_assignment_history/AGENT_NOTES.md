# AGENT_NOTES.md - Employee Assignment History

## 1. Module Purpose
Tracks asset custody per employee: which **equipment** or **inventory item** (optional) an employee holds, assignment and return dates, handover metadata, and who recorded the change. The module is the read/write CRUD surface for `employee_assignment_history`; **equipment assignee changes also upsert rows via** `modules/equipment/equipment_assignment_sync.php` (do not duplicate that logic here).

## 2. Key Tables
- **employee_assignment_history** — one row per `(company_id, employee_id)` (unique key); stores the current assignment snapshot for that employee, not an unbounded event log.

## 3. Required Relationships
- **employee_assignment_history** → depends on **companies** (`company_id`, `ON DELETE CASCADE`).
- **employee_assignment_history** → depends on **employees** (`employee_id`, `ON DELETE RESTRICT`).
- **employee_assignment_history** → optional **equipment** (`equipment_id`, `ON DELETE SET NULL`).
- **employee_assignment_history** → optional **inventory_items** (`inventory_item_id`, `ON DELETE SET NULL`).
- **employee_assignment_history** → optional **users** (`assigned_by_employee_id`, `received_by_employee_id`, `ON DELETE SET NULL`).
- **equipment** → optional mirror FK **employees** (`assigned_to_employee_id`) — kept in sync when assignment originates from the equipment module.

## 4. Business Rules (Critical for Agents)
- **One row per employee per company:** `UNIQUE KEY uq_employee_assignment_history_company_scope (company_id, employee_id)`. Upserts replace the same row; do not insert duplicate `(company_id, employee_id)` pairs.
- **Required columns:** `employee_id`, `assigned_date` (`date NOT NULL`). Optional: `equipment_id`, `inventory_item_id`, `asset_description`, `returned_date`, `assigned_by_employee_id`, `received_by_employee_id`, `condition_on_return`, `signed_handover`, `comments`, `sim_imei`.
- **Open assignment:** `returned_date IS NULL` means the employee still holds the linked asset (when `equipment_id` or `inventory_item_id` is set).
- **Inbound sync from equipment (mandatory contract):** when **Assign To Employee** changes on `modules/equipment/create.php`, `equipment_sync_assigned_employee()` (in `modules/equipment/equipment_assignment_sync.php`) runs inside the equipment save transaction and:
  - Sets `equipment.assigned_to_employee_id`.
  - **Assign:** UPSERT history for the employee with `equipment_id`, `assigned_date = DATE(COALESCE(equipment.updated_at, equipment.created_at))`, `returned_date = NULL`, `assigned_by_employee_id =` session user, `asset_description` from equipment name/model. **Replace policy:** clears assignee on any other equipment that employee held.
  - **Unassign:** clears `equipment.assigned_to_employee_id`; closes open rows for that `equipment_id` (`returned_date = CURDATE()`).
  - **Delete equipment:** closes open rows for that `equipment_id` before `DELETE` (`returned_date = CURDATE()`); FK clears `equipment_id` on history.
- **Equipment form hidden fields (posted, not shown in this module):** `equipment_id` (edit id, `0` on create) and `assigned_date` (date from equipment timestamps on load; sync re-reads `DATE(COALESCE(updated_at, created_at))` after save). See `modules/equipment/AGENT_NOTES.md`.
- **Manual CRUD in this module:** admins may still create/edit rows here; avoid fighting equipment sync (same unique key and `equipment_id` / `returned_date` semantics).
- **No `assignment_types` column** — do not reference `assignment_types` in inserts or docs for this table.

## 5. UI Behavior Requirements
- **Standard flattened CRUD** (`index.php` with `$crud_table = 'employee_assignment_history'`).
- **Employee labels:** FK dropdowns and list cells use `TRIM(CONCAT(first_name, ' ', last_name))` with fallback to `display_name` (same pattern as `index.php` `cr_fk_options` / `cr_fk_label` for `employees`).
- **Chronological use:** sort/list by `assigned_date`, `returned_date`, or `id` as needed; unique key is per employee, not per assignment event.
- **Hide** `company_id` from list/view (flattened CRUD `$hideCompanyIdTables` includes this table).

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import when enabled on index.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` (wrappers route to `index.php` with `$crud_action`).
- **Equipment sync (external writer):** `modules/equipment/equipment_assignment_sync.php` — not part of this folder; do not move sync into this module.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id` on all queries and inserts.
- `employee_id`, `equipment_id`, and `inventory_item_id` must belong to the active company when set.

## 9. Audit Logging Requirements
- Database triggers: `trg_employee_assignment_history_audit_insert`, `trg_employee_assignment_history_audit_update`, `trg_employee_assignment_history_audit_delete` on `employee_assignment_history` in `db/03_triggers.sql` (JSON includes `equipment_id`, `assigned_date`, `returned_date`, `asset_description`, user FKs, etc.).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Stale template references:** there is no `assignment_types` FK or `notes`-only insert shape — use real columns from `db/01_schema.sql`. [Cursor-Valid]
- **Deleting history** to end an assignment — prefer setting `returned_date` (equipment unassign/delete closes rows automatically). [Cursor-Valid]
- **Duplicate employees:** unique `(company_id, employee_id)` rejects a second row; use UPDATE/UPSERT semantics. [Cursor-Valid]
- **Equipment sync vs manual edit:** changing `equipment_id` here without updating `equipment.assigned_to_employee_id` desynchronises custody; prefer assignee changes on the equipment form. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant-scoped, open equipment assignment)
```php
$stmt = $conn->prepare(
    'SELECT * FROM employee_assignment_history
     WHERE employee_id = ? AND company_id = ? AND returned_date IS NULL LIMIT 1'
);
$stmt->bind_param('ii', $employeeId, $companyId);
$stmt->execute();
```

### Safe UPSERT (equipment sync shape — prefer `equipment_sync_assigned_employee()`)
```php
$stmt = $conn->prepare(
    'INSERT INTO employee_assignment_history
        (company_id, employee_id, equipment_id, assigned_date, returned_date, assigned_by_employee_id, asset_description, active)
     VALUES (?, ?, ?, ?, NULL, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
        equipment_id = VALUES(equipment_id),
        assigned_date = VALUES(assigned_date),
        returned_date = NULL,
        assigned_by_employee_id = VALUES(assigned_by_employee_id),
        asset_description = VALUES(asset_description),
        updated_at = CURRENT_TIMESTAMP'
);
$stmt->bind_param('iiisis', $companyId, $employeeId, $equipmentId, $assignedDate, $assignedByUserId, $assetDescription);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Custody timeline for HR/asset audits. Equipment module is the primary writer for equipment-linked rows; this module is the admin/reference CRUD view of the same table.
