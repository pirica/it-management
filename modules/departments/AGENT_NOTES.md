# AGENT_NOTES.md - Departments

## 1. Module Purpose
Manages company departments and their contact info (email, extension, etc.).

## 2. Key Tables
- **departments** — main department storage.

## 3. Required Relationships
- **departments** → depends on **companies**.
- **departments** → referenced by **employees**, **cost_centers**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique per company.
- **Tenant Scope**: Isolated per company.

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

## 10. Common Pitfalls
- Deleting a department with active employees or cost centers. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM departments WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO departments (company_id, name, code) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $companyId, $name, $code);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Primary organizational unit.
