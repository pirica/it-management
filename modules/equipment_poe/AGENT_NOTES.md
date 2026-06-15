# AGENT_NOTES.md - Equipment PoE

## 1. Module Purpose
Tracks Power over Ethernet (PoE) capabilities and usage for equipment (primarily switches).

## 2. Key Tables
- **equipment_poe** — stores PoE specifications.

## 3. Required Relationships
- **equipment_poe** → depends on **companies**.
- **equipment_poe** → depends on **equipment**.

## 4. Business Rules (Critical for Agents)
- **Power Budgeting**: Used to track total PoE budget and current consumption.

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
Essential for network planning and preventing power overloads on switches.
