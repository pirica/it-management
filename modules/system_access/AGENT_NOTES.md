# AGENT_NOTES.md - System Access

## 1. Module Purpose
Lookup table for the various systems and applications used within the company.

## 2. Key Tables
- **system_access** — stores system names (e.g., "ERP", "Email").

## 3. Required Relationships
- **system_access** → depends on **companies**.
- **system_access** → referenced by **employee_system_access**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Defines the list of applications managed for access control.
