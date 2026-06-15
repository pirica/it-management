# AGENT_NOTES.md - Role Hierarchy

## 1. Module Purpose
Defines the hierarchical relationship between different user roles for inheritance and permissions.

## 2. Key Tables
- **role_hierarchy** — mapping of parent-child role relationships.

## 3. Required Relationships
- **role_hierarchy** → depends on **companies**.
- **role_hierarchy** → depends on **user_roles**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Determines how permissions flow between roles.
