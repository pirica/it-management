# AGENT_NOTES.md - Equipment RJ45

## 1. Module Purpose
Tracks standard RJ45 copper connections and port configurations for equipment.

## 2. Key Tables
- **equipment_rj45** — stores RJ45-specific data.

## 3. Required Relationships
- **equipment_rj45** → depends on **companies**.
- **equipment_rj45** → depends on **equipment**.

## 4. Business Rules (Critical for Agents)
- **Standard Patching**: Maps copper connections from devices to patch panels or switches.

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
The most common connection type in the system.
