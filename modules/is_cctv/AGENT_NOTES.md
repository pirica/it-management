# AGENT_NOTES.md - Is Cctv

## 1. Module Purpose
A filtered view of the Equipment module specifically for Cctv devices.

## 2. Key Tables
- Reads from **equipment** and **equipment_types**.

## 3. Required Relationships
- Depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Filter**: Strictly filters equipment where `equipment_type_name = 'Cctv'`.
- **Inheritance**: Uses standard Equipment CRUD but restricts scope.

## 5. UI Behavior Requirements
- **Standard Equipment CRUD** (filtered).

## 6. API Actions (If Applicable)
- None (inherits from Equipment).

## 7. File Structure
- **index.php** — wraps `../equipment/index.php` with a filter.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 12. Module Owner Notes (Optional)
Provides a specialized view for Cctv asset management.
