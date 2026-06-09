# AGENT_NOTES.md - Equipment Fiber Patch

## 1. Module Purpose
Tracks fiber patch panel connections.

## 2. Key Tables
- **equipment_fiber_patch** — stores patch connection details.

## 3. Required Relationships
- **equipment_fiber_patch** → depends on **companies**.
- **equipment_fiber_patch** → depends on **equipment**.

## 4. Business Rules (Critical for Agents)
- **Point-to-Point**: Maps connections between fiber ports.

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
Critical for physical layer network mapping.
