# AGENT_NOTES.md - Is Phone

## 1. Module Purpose
A filtered view of the Equipment module specifically for phone devices (desk phones, VoIP handsets, etc.). Provides a type-scoped sidebar entry for telephony and IT teams without the full equipment catalogue.

## 2. Key Tables
- **equipment** — main asset records (read/write via `modules/equipment/`).
- **equipment_types** — joined for the `Phone` type filter (`et.name`).

## 3. Required Relationships
- **equipment** → depends on **companies** (`company_id`).
- **equipment** → depends on **equipment_types** (`equipment_type_id`; filter matches `Phone`).
- **equipment** → may link to **manufacturers**, **equipment_statuses**, **it_locations**, **racks**, **idfs**, and **employees** (same as parent Equipment module).

## 4. Business Rules (Critical for Agents)
- **Filter:** List and view enforce `LOWER(TRIM(et.name)) = 'phone'` via `$equipmentTypeNameFilter = 'Phone'` in `index.php` / `view.php`.
- **Inheritance:** All CRUD behaviour lives in `modules/equipment/`; this folder only sets wrapper variables.
- **List restrictions:** `index.php` sets `$equipmentAllowCreate`, `$equipmentAllowDelete`, and `$equipmentAllowImport` to `false`.

## 5. UI Behavior Requirements
- **Standard Equipment list** (search, sort, pagination, export) scoped to phones.
- **View/edit** routes through wrapper files with the same type filter guard on detail screens.
- **company_id** hidden from UI (inherited from equipment).

## 6. API Actions (If Applicable)
- None in this folder. Inherits equipment JSON handlers from `modules/equipment/index.php` when invoked on the parent module.

## 7. File Structure
- **index.php** — sets `$equipmentTypeNameFilter = 'Phone'` and wrapper flags; `require '../equipment/index.php'`.
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
- INSERT, UPDATE, and DELETE on `equipment` are logged by MySQL triggers `trg_equipment_audit_insert`, `trg_equipment_audit_update`, and `trg_equipment_audit_delete` (see `database.sql`).
- Entries are written to **audit_logs** with `table_name = 'equipment'` and JSON payloads; session context comes from `@app_user_id` / `@app_company_id` in `config/config.php`.
- This façade has no local audit handlers.

## 10. Common Pitfalls
- **Protection Zone:** `modules/equipment/` is in the Protection Zone — do not change its logic or structure unless explicitly requested (`AGENTS.md`).
- **Do not delete canonical wrappers:** Keep `modules/is_phone/` and sibling `is_*` folders.
- **Type filter:** `$equipmentTypeNameFilter` must match `equipment_types.name` (`Phone`).
- **Do not copy equipment CRUD** into this folder.

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant + type filter)
```php
$typeName = 'Phone';
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
Provides a specialised view for phone asset management alongside the general Equipment module.
