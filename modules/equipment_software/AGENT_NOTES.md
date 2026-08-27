# AGENT_NOTES.md - Equipment Software

## 1. Module Purpose
Junction between **equipment** and the **software** catalog. Dates are **not** stored here — they live on `software`. Prefer linking products from equipment create/edit (`software_ids[]` + `itm_equipment_software_sync()`). This flattened CRUD exists for lookup/admin of links.

## 2. Key Tables
- **equipment_software** — `company_id`, `equipment_id`, `software_id`, unique `(company_id, equipment_id, software_id)`, audit/soft-delete.

## 3. Required Relationships
- **equipment_software** → **companies** (CASCADE).
- **equipment_software** → **equipment** (`equipment_id`, CASCADE).
- **equipment_software** → **software** (`software_id`, RESTRICT).

## 4. Business Rules (Critical for Agents)
- Sync from equipment save restores soft-deleted links when the same software is re-selected, and soft-deletes removed links.
- Do not add date columns on this table.

## 5. UI Behavior Requirements
- Standard flattened CRUD materialized from manufacturers. FK labels for `equipment_id` / `software_id`. Hide `company_id`.

## 6. API Actions (If Applicable)
- **import_excel_rows** on `index.php`.

## 7. File Structure
- Standard CRUD entry files. Helper: `includes/itm_software_eol.php`. Doc: `docs/SOFTWARE_EOL.md`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`. Equipment and software ids must belong to the same tenant.

## 9. Audit Logging Requirements
- Triggers `trg_equipment_software_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls
- **Soft-delete + audit meta:** same scaffold contract as other `docs/list_soft-delete.txt` modules. [Cursor-Valid]
- CASCADE from equipment delete removes links; RESTRICT from software delete blocks catalog delete while live links exist. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe sync
```php
$err = itm_equipment_software_sync($conn, $companyId, $equipmentId, $softwareIds, $employeeId);
```

## 12. Module Owner Notes (Optional)
Module: [modules/equipment_software/index.php](http://localhost/it-management/modules/equipment_software/index.php) (open in a new browser tab).
