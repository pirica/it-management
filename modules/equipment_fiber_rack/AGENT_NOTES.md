# AGENT_NOTES.md - Equipment Fiber Rack

## 1. Module Purpose
Manages fiber rack placements and specifications.

## 2. Key Tables
- **equipment_fiber_rack** — stores rack-related fiber data.

## 3. Required Relationships
- **equipment_fiber_rack** → depends on **companies**.
- **equipment_fiber_rack** → depends on **racks**.

## 4. Business Rules (Critical for Agents)
- **Scoped to Rack**: Tracks fiber distribution within a specific rack.

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
Used in data center and IDF management.
