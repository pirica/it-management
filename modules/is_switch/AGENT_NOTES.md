# AGENT_NOTES.md - Is Switch

## 1. Module Purpose
A filtered view of the Equipment module specifically for network switch devices. Provides a type-scoped sidebar entry with switch port manager support (inherited from equipment when the type filter is `Switch`).

## 2. Key Tables
- **equipment** — main asset records (read/write via `modules/equipment/`).
- **equipment_types** — joined for the `Switch` type filter (`et.name`).
- **switch_ports** — port tiles and IDF sync (managed in `modules/equipment/`, not duplicated here).

## 3. Required Relationships
- **equipment** → depends on **companies** (`company_id`).
- **equipment** → depends on **equipment_types** (`equipment_type_id`; filter matches `Switch`).
- **equipment** → may link to **idfs**, **switch_ports**, **idf_ports**, and related IDF tables (IDF sync guardrails apply in `modules/equipment/`).
- **equipment** → may link to **manufacturers**, **equipment_statuses**, **it_locations**, **racks**, and **employees**.

## 4. Business Rules (Critical for Agents)
- **Filter:** List and view enforce `LOWER(TRIM(et.name)) = 'switch'` via `$equipmentTypeNameFilter = 'Switch'` in `index.php` / `view.php`.
- **Inheritance:** All CRUD and switch port manager behaviour lives in `modules/equipment/`; this folder only sets wrapper variables.
- **Switch port manager:** Enabled when `$equipmentTypeNameFilter` is `Switch` (or empty on the general equipment module) — see `$enableSwitchPortManager` in `modules/equipment/index.php`.
- **IDF synchronization:** Create/Edit/Update/Delete on switches must keep IDF-related tables aligned per `AGENTS.md`; run `php scripts/idfs_sync_human_test.php` after equipment changes.
- **Edit gap:** `edit.php` does not set `$equipmentTypeNameFilter`; only list/view wrappers scope by type.
- **List restrictions:** `index.php` sets `$equipmentAllowCreate`, `$equipmentAllowDelete`, and `$equipmentAllowImport` to `false`.

## 5. UI Behavior Requirements
- **UI configuration reviewed:** Gate-excluded prefix `is_*` — list/search/sort/pagination/export/bulk/new-button checks are `[n/a][fail|n/a][reviewed]` via registry key `is_*` in `scripts/data/ui_configuration_reviewed.json` (façade `index.php` only `require`s equipment).
- **Standard Equipment list** (search, sort, pagination, export) scoped to switches.
- **Switch port manager** tiles and RJ45/SFP icon mapping (Unknown vs active PNG paths) inherited from equipment.
- **List and view** enforce the type filter via wrapper variables; **edit** routes to `equipment/edit.php` without setting `$equipmentTypeNameFilter` — a direct edit URL can open any company-scoped equipment id (type filter not enforced on edit; `company_id` still enforced in `equipment/create.php`).
- **company_id** hidden from UI (inherited from equipment).

## 6. API Actions (If Applicable)
- None in this folder. Inherits **import_excel_rows** JSON import only from `modules/equipment/index.php` when the façade `index.php` requires equipment index; switch port manager uses form POST under equipment.

## 7. File Structure
- **index.php** — sets `$equipmentTypeNameFilter = 'Switch'` and wrapper flags; `require '../equipment/index.php'`.
- **view.php** — sets type filter; `require '../equipment/view.php'`.
- **create.php** — `require '../equipment/create.php'`.
- **edit.php** — `require '../equipment/edit.php'`.
- **delete.php** — `require '../equipment/delete.php'`.
- **list_all.php** — `require '../equipment/list_all.php'`.
- **view_all.php** — `require 'list_all.php'`.

## 8. Multi-Tenant Rules
- All queries inherit `WHERE e.company_id = $company_id` from `modules/equipment/index.php`.
- Switch ports and IDF links are company-scoped through the parent equipment module.

## 9. Audit Logging Requirements
- INSERT, UPDATE, and DELETE on `equipment` are logged by MySQL triggers `trg_equipment_audit_insert`, `trg_equipment_audit_update`, and `trg_equipment_audit_delete` (see `db/03_triggers.sql`).
- Entries are written to **audit_logs** with `table_name = 'equipment'` and JSON payloads (including switch-specific columns); session context comes from `@app_employee_id` / `@app_company_id` in `config/config.php`.
- This façade has no local audit handlers.

## 10. Common Pitfalls
- **Do not delete canonical wrappers:** Keep `modules/is_switch/` and sibling `is_*` folders. [Cursor-Valid]
- **Type filter:** `$equipmentTypeNameFilter` must be `Switch` for port manager UI and correct list scoping. [Cursor-Valid]
- **IDF partial updates:** Never update `equipment` / `switch_ports` / `idf_ports` in isolation — use equipment transaction paths. [Cursor-Valid]
- **Do not copy equipment CRUD** into this folder. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant + type filter)
```php
$typeName = 'Switch';
$stmt = $conn->prepare(
    "SELECT e.id, e.name, e.hostname, e.idf_id
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
Primary sidebar entry for switch inventory; switch port manager and IDF sync behaviour are owned by `modules/equipment/`.
