# AGENT_NOTES.md - Equipment Fiber Count

## 1. Module Purpose
Lookup table for fiber strand counts (e.g., "12", "24", "48").

## 2. Key Tables
- **equipment_fiber_count** — stores count values.

## 3. Required Relationships
- **equipment_fiber_count** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Count**: The numeric count must be unique per company.

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

## 12. Module Owner Notes (Optional)
Ensures consistency in fiber infrastructure documentation.
