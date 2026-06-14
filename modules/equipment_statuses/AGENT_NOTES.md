# AGENT_NOTES.md - Equipment Statuses

## 1. Module Purpose
Lookup table for asset lifecycle statuses (e.g., "Active", "Retired", "In Repair", "Storage").

## 2. Key Tables
- **equipment_statuses** — stores status names and active flags.

## 3. Required Relationships
- **equipment_statuses** → depends on **companies**.
- **equipment_statuses** → referenced by **equipment**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Status name must be unique within a company.

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
$stmt = $conn->prepare("SELECT * FROM equipment_statuses WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Crucial for asset management and reporting.
