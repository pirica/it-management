# AGENT_NOTES.md - IT Locations

## 1. Module Purpose
Manages physical locations where IT equipment or infrastructure is housed (e.g., "MDF", "IDF 1", "Server Room").

## 2. Key Tables
- **it_locations** — main location records.

## 3. Required Relationships
- **it_locations** → depends on **companies**.
- **it_locations** → depends on **location_types** (via `it_location_type_id`).
- **it_locations** → referenced by **equipment**, **idfs**, **floor_designer**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Location name must be unique within a company.
- **Nullability**: `it_location_type_id` should be handleable as null if not specified.

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
- **Restrictive Deletes**: Deleting a location that is referenced by equipment or IDFs will typically fail due to FK constraints. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM it_locations WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Foundational for physical asset tracking.
