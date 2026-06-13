# AGENT_NOTES.md - Suppliers

## 1. Module Purpose
Manages vendor and supplier information for equipment and services.

## 2. Key Tables
- **suppliers** — main supplier data.

## 3. Required Relationships
- **suppliers** → depends on **companies**.
- **suppliers** → depends on **supplier_statuses**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Supplier name must be unique within a company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Centralized contact point for all IT vendors.
