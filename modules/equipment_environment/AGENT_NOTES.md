# AGENT_NOTES.md - Equipment Environment

## 1. Module Purpose
Lookup table for equipment environments (e.g., "Production", "Staging", "Development").

## 2. Key Tables
- **equipment_environment** — stores environment names and status.

## 3. Required Relationships
- **equipment_environment** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Environment name must be unique per company.

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
$stmt = $conn->prepare("SELECT * FROM equipment_environment WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Helps in categorizing assets for better lifecycle management.
