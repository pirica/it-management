# AGENT_NOTES.md - Forecast Revisions Status

## 1. Module Purpose
Lookup table for status states of forecast revisions (e.g., "Draft", "Submitted", "Approved").

## 2. Key Tables
- **forecast_revisions_status** — stores status names and notes.

## 3. Required Relationships
- **forecast_revisions_status** → depends on **companies**.
- **forecast_revisions_status** → referenced by **forecast_revisions**.

## 4. Business Rules (Critical for Agents)
- **Unique Status**: Status name must be unique per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Defines the lifecycle of a forecast.
