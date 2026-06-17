# AGENT_NOTES.md - User Roles

## 1. Module Purpose
Lookup table for system roles (e.g., "Admin", "IT Staff", "User").

## 2. Key Tables
- **user_roles** — stores role names and status.

## 3. Required Relationships
- **user_roles** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
The basis for the RBAC permission system.
