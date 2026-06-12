# AGENT_NOTES.md - Equipment Types

## 1. Module Purpose
Lookup table for categories of IT equipment (e.g., "Server", "Workstation", "Switch").

## 2. Key Tables
- **equipment_types** — stores type names, codes, and UI emojis.

## 3. Required Relationships
- **equipment_types** → depends on **companies**.
- **equipment_types** → referenced by **equipment**, **catalogs**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Type name must be unique within a company.
- **UI Emojis**: Includes an emoji field for visual categorization in the UI.

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
$stmt = $conn->prepare("SELECT * FROM equipment_types WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary way assets are categorized across the system.
