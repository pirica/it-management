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
- **equipment** → links to **employees** (via `assigned_to_employee_id`).

## 4. Business Rules (Critical for Agents)
- **Asset Tagging**: Each item should ideally have a unique serial or asset number within the company.
- **Type-Specific Logic**: Modules like `is_switch` or `is_server` might extend the logic for specific equipment types.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Photo Upload**: Supports uploading photos of the equipment.
- **Search & Filter**: Extensive filtering by type, status, and assignment.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure + `delete_functions.php`.

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
$stmt = $conn->prepare("INSERT INTO equipment (company_id, equipment_type_id, hostname) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $companyId, $typeId, $hostname);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary inventory module.
