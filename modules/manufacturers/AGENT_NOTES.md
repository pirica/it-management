# AGENT_NOTES.md - Manufacturers

## 1. Module Purpose
Lookup table for equipment and inventory manufacturers (e.g., "Dell", "Cisco", "HP").

## 2. Key Tables
- **manufacturers** — stores manufacturer names and status.

## 3. Required Relationships
- **manufacturers** → depends on **companies**.
- **manufacturers** → referenced by **equipment**, **catalogs**, **inventory_items**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Manufacturer name must be unique per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM manufacturers WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Central lookup for asset branding.
