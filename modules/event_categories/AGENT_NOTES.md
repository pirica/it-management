# AGENT_NOTES.md - Event Categories

## 1. Module Purpose
Lookup table for categorizing events and alerts (e.g., "Maintenance", "Holiday", "Meeting"). Includes color coding for calendar display.

## 2. Key Tables
- **event_categories** — stores category names and hex colors.

## 3. Required Relationships
- **event_categories** → depends on **companies**.
- **event_categories** → referenced by **events** and **alerts**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique within a company.
- **Hex Color**: Should be a valid CSS hex color for visualization on the calendar.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Color Swatch**: List and view should show the selected color.

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
$stmt = $conn->prepare("SELECT * FROM event_categories WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Directly impacts the visual layout of the Calendar module.
