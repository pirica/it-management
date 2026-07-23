# AGENT_NOTES.md - Is CCTV

## 1. Module Purpose
A filtered view of the Equipment module specifically for CCTV devices. Provides a type-scoped sidebar entry for security and facilities teams to manage camera assets without the full equipment catalogue.

## 2. Key Tables
- **equipment** — main asset records (read/write via `modules/equipment/`).
- **equipment_types** — joined for the `CCTV` type filter (`et.name`).

## 3. Required Relationships
- **equipment** → depends on **companies** (`company_id`).
- **equipment** → depends on **equipment_types** (`equipment_type_id`; filter matches `CCTV`).
- **equipment** → may link to **manufacturers**, **equipment_statuses**, **it_locations**, **racks**, **idfs**, and **employees** (same as parent Equipment module).

## 4. Business Rules (Critical for Agents)
- **Filter:** List and view enforce `LOWER(TRIM(et.name)) = 'cctv'` via `$equipmentTypeNameFilter = 'CCTV'` in `index.php` / `view.php`.
- **Inheritance:** All CRUD behaviour lives in `modules/equipment/`; this folder only sets wrapper variables.
- **Edit gap:** `edit.php` does not set `$equipmentTypeNameFilter`; only list/view wrappers scope by type.
- **List restrictions:** `index.php` sets `$equipmentAllowCreate`, `$equipmentAllowDelete`, and `$equipmentAllowImport` to `false`.

## 5. UI Behavior Requirements
- **Standard Equipment list** (search, sort, pagination, export) scoped to CCTV devices.
- **List and view** enforce the type filter via wrapper variables; **edit** routes to `equipment/edit.php` without setting `$equipmentTypeNameFilter` — a direct edit URL can open any company-scoped equipment id (type filter not enforced on edit; `company_id` still enforced in `equipment/create.php`).
- **company_id** hidden from UI (inherited from equipment).

## 6. API Actions (If Applicable)
- **import_excel_rows** only — JSON bulk import via `modules/equipment/index.php` when this façade's `index.php` requires equipment index.

## 7. File Structure
- **index.php** — sets `$equipmentTypeNameFilter = 'CCTV'` and wrapper flags; `require '../equipment/index.php'`.
- **view.php** — sets type filter; `require '../equipment/view.php'`.
- **create.php** — `require '../equipment/create.php'`.
- **edit.php** — `require '../equipment/edit.php'`.
- **delete.php** — `require '../equipment/delete.php'`.
- **list_all.php** — `require '../equipment/list_all.php'`.
- **view_all.php** — `require 'list_all.php'`.

## 8. Multi-Tenant Rules
- All queries inherit `WHERE e.company_id = $company_id` from `modules/equipment/index.php`.
- Equipment rows cannot be moved between companies from this façade.

## 9. Audit Logging Requirements
- INSERT, UPDATE, and DELETE on `equipment` are logged by MySQL triggers `trg_equipment_audit_insert`, `trg_equipment_audit_update`, and `trg_equipment_audit_delete` (see `db/03_triggers.sql`).
- Entries are written to **audit_logs** with `table_name = 'equipment'` and JSON payloads; session context comes from `@app_employee_id` / `@app_company_id` in `config/config.php`.
- This façade has no local audit handlers.

## 10. Common Pitfalls
- **Do not delete canonical wrappers:** Keep `modules/is_cctv/` and sibling `is_*` folders. [Cursor-Valid]
- **Type filter:** Use `CCTV` (not `Cctv`) — must match `equipment_types.name` case-insensitively via `LOWER(TRIM(et.name))`. [Cursor-Valid]
- **Do not copy equipment CRUD** into this folder. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant + type filter)
```php
$typeName = 'CCTV';
$stmt = $conn->prepare(
    "SELECT e.id, e.name, e.hostname
     FROM equipment e
     INNER JOIN equipment_types et ON et.id = e.equipment_type_id
     WHERE e.company_id = ? AND LOWER(TRIM(et.name)) = LOWER(TRIM(?))"
);
$stmt->bind_param('is', $companyId, $typeName);
$stmt->execute();
```

### Safe single-row fetch
```php
$stmt = $conn->prepare('SELECT * FROM equipment WHERE company_id = ? AND id = ?');
$stmt->bind_param('ii', $companyId, $equipmentId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Provides a specialised view for CCTV asset management alongside the general Equipment module.
