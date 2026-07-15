# AGENT_NOTES.md - Companies

## 1. Module Purpose
Manages the top-level tenant entities ("Companies") in the multi-tenant system. All other data is scoped to one of these records.

## 2. Key Tables
- **companies** — core tenant records.

## 3. Required Relationships
- The **root** of almost all relationships in the system.

## 4. Business Rules (Critical for Agents)
- **Unique Names**: `company` name must be unique.
- **Unique Incode**: `incode` (a 6-character short code) must be unique.
- **Active Status**: Inactivating a company should theoretically block access to its data, though implementation depends on session logic.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Dashboard Integration**: Selected company is stored in `$_SESSION['company_id']`.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- This is the only module that is **NOT** scoped by `company_id` in its primary list, as it lists the companies themselves.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Deleting Companies**: Highly destructive; will cascade delete almost all data in the system due to foreign key constraints.
- **Incode Length**: Must be 6 characters or fewer.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO companies (company, incode, active) VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $name, $incode, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The bedrock of the application's multi-tenancy.
