# AGENT_NOTES.md - Patches & Updates

## 1. Module Purpose
Tracks software patches, security updates, and system upgrades across equipment.

## 2. Key Tables
- **patches_updates** — main patch records.

## 3. Required Relationships
- **patches_updates** → depends on **companies**.
- **patches_updates** → depends on **equipment**.
- **patches_updates** → depends on **patches_updates_level**.
- **patches_updates** → depends on **patches_updates_status**.

## 4. Business Rules (Critical for Agents)
- **Release Tracking**: Monitors the lifecycle of a patch from "Planned" to "Installed".
- **Severity Levels**: Categorizes patches by importance (e.g., "Critical", "Optional").

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Photo Upload**: Supports screenshots of patch confirmation or errors.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM patches_updates WHERE company_id = ? AND equipment_id = ?");
$stmt->bind_param("ii", $companyId, $equipmentId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for security compliance and vulnerability management.
