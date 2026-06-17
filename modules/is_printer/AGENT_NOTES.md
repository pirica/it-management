# AGENT_NOTES.md - Is Printer

## 1. Module Purpose
A filtered view of the Equipment module specifically for printer devices. Provides a type-scoped sidebar entry for print services and IT teams without the full equipment catalogue.

## 2. Key Tables
- **equipment** — main asset records (read/write via `modules/equipment/`).
- **equipment_types** — joined for the `Printer` type filter (`et.name`).

## 3. Required Relationships
- **equipment** → depends on **companies** (`company_id`).
- **equipment** → depends on **equipment_types** (`equipment_type_id`; filter matches `Printer`).
- **equipment** → may link to **manufacturers**, **equipment_statuses**, **it_locations**, **racks**, **idfs**, and **employees** (same as parent Equipment module).
- Printer-specific columns on **equipment** (`printer_device_type_id`, `printer_color_capable`, `printer_scan`) are edited via equipment forms.

## 4. Business Rules (Critical for Agents)
- **Filter:** List and view enforce `LOWER(TRIM(et.name)) = 'printer'` via `$equipmentTypeNameFilter = 'Printer'` in `index.php` / `view.php`.
- **Inheritance:** All CRUD behaviour lives in `modules/equipment/`; this folder only sets wrapper variables.
- **Edit gap:** `edit.php` does not set `$equipmentTypeNameFilter`; only list/view wrappers scope by type.
- **List restrictions:** `index.php` sets `$equipmentAllowCreate`, `$equipmentAllowDelete`, and `$equipmentAllowImport` to `false`.

## 5. UI Behavior Requirements
- **Standard Equipment list** (search, sort, pagination, export) scoped to printers.
- **List and view** enforce the type filter via wrapper variables; **edit** routes to `equipment/edit.php` without setting `$equipmentTypeNameFilter` — a direct edit URL can open any equipment id (known gap; document, do not claim edit is type-guarded).
- **company_id** hidden from UI (inherited from equipment).

## 6. API Actions (If Applicable)
- **import_excel_rows** and other JSON handlers in `modules/equipment/index.php` also run when requests hit this façade's `index.php` (wrapper `require`s equipment index).

## 7. File Structure
- **index.php** — sets `$equipmentTypeNameFilter = 'Printer'` and wrapper flags; `require '../equipment/index.php'`.
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
- Entries are written to **audit_logs** with `table_name = 'equipment'` and JSON payloads (including printer-specific fields); session context comes from `@app_user_id` / `@app_company_id` in `config/config.php`.
- This façade has no local audit handlers.

## 10. Common Pitfalls
- **Protection Zone:** `modules/equipment/` is in the Protection Zone — do not change its logic or structure unless explicitly requested (`AGENTS.md`).
- **Do not delete canonical wrappers:** Keep `modules/is_printer/` and sibling `is_*` folders.
- **Type filter:** `$equipmentTypeNameFilter` must match `equipment_types.name` (`Printer`).
- **Do not copy equipment CRUD** into this folder.

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant + type filter)
```php
$typeName = 'Printer';
$stmt = $conn->prepare(
    "SELECT e.id, e.name, e.printer_color_capable
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
Provides a specialised view for printer asset management alongside the general Equipment module.
