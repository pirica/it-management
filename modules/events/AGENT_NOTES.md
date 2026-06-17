# AGENT_NOTES.md - Events

## 1. Module Purpose
Manages scheduled events, meetings, and maintenance windows.

## 2. Key Tables
- **events** — main event data.

## 3. Required Relationships
- **events** → depends on **companies**.
- **events** → depends on **event_categories**.
- **events** → links to **users** (via `assigned_to_user_id`).

## 4. Business Rules (Critical for Agents)
- **Date Validation**: `start_datetime` should generally be before `end_datetime`.
- **Visibility**: Similar to alerts, may have assignment-based visibility logic depending on the implementation.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **ICS Export**: Often supports exporting to iCalendar format.

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
$stmt = $conn->prepare("SELECT * FROM events WHERE company_id = ? AND start_datetime >= ?");
$stmt->bind_param("is", $companyId, $startDate);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary source of data for the Calendar module.
