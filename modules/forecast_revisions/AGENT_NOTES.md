# AGENT_NOTES.md - Forecast Revisions

## 1. Module Purpose
Manages revisions to financial forecasts for a specific month and year.

## 2. Key Tables
- **forecast_revisions** — main revision data.

## 3. Required Relationships
- **forecast_revisions** → depends on **companies**.
- **forecast_revisions** → depends on **cost_centers**.
- **forecast_revisions** → depends on **gl_accounts**.
- **forecast_revisions** → depends on **forecast_revisions_status**.

## 4. Business Rules (Critical for Agents)
- **Unique Revision**: Only one revision record allowed per combination of company, cost center, GL account, year, and month.
- **Locking**: Once a revision is "Approved" or "Locked", it should generally not be editable without proper permissions.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Approval Flow**: Integrates with the Approvals module for multi-stage reviews.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Duplicate Forecasts**: Unique constraint violations on inserts. [Valid]-[2026-07-15]
- **Locked Edits**: Attempting to edit a revision that has been finalized. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM forecast_revisions WHERE company_id = ? AND year = ? AND month = ?");
$stmt->bind_param("iii", $companyId, $year, $month);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The dynamic part of the budgeting system.
