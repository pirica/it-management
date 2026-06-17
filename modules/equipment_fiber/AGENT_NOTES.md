# AGENT_NOTES.md - Equipment Fiber

## 1. Module Purpose
Manages fiber optic connections and specifications for equipment.

## 2. Key Tables
- **equipment_fiber** — stores fiber-specific data for assets.

## 3. Required Relationships
- **equipment_fiber** → depends on **companies**.
- **equipment_fiber** → depends on **equipment**.

## 4. Business Rules (Critical for Agents)
- **Scoped to Equipment**: Each record should relate to a specific piece of equipment that supports fiber.

## 5. UI Behavior Requirements
- **Standard CRUD**.

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
$stmt = $conn->prepare("SELECT * FROM equipment_fiber WHERE company_id = ? AND equipment_id = ?");
$stmt->bind_param("ii", $companyId, $equipmentId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Used for high-speed network infrastructure tracking.
