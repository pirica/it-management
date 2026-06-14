# AGENT_NOTES.md - Calendar

## 1. Module Purpose
Provides a central calendar view for managing and visualizing scheduled events and alerts.

## 2. Key Tables
- Reads from **events** and **alerts**.

## 3. Required Relationships
- Depends on **companies**.
- Linked to **event_categories** for color coding.

## 4. Business Rules (Critical for Agents)
- **Aggregated View**: This is a visualization module for other record types.
- **Tenant Isolation**: Only shows records belonging to the active `company_id`.

## 5. UI Behavior Requirements
- **Interactive Calendar**: Typically uses a library like FullCalendar (but check local implementation).
- **Filtering**: Allows filtering by event category or alert type.

## 6. API Actions (If Applicable)
- None (Visualization only).

## 7. File Structure
- **index.php** — main calendar interface.

## 8. Multi-Tenant Rules
- All data fetch operations must filter by `company_id`.

## 9. Audit Logging Requirements
- None for the calendar itself; mutations happen in the source modules (Events/Alerts).

## 10. Common Pitfalls
- **Timezone Mismatches**: Ensure dates are correctly formatted for the calendar library.
- **Overlapping Events**: High density of events can make the calendar difficult to read.

## 11. Examples of Safe Code Patterns

### Safe SELECT (Events for Calendar)
```php
$stmt = $conn->prepare("SELECT id, title, start_datetime as start, end_datetime as end FROM events WHERE company_id = ? AND active = 1");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Central hub for planning.
