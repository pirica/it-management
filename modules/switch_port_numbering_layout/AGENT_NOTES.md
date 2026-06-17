# AGENT_NOTES.md - Switch Port Numbering Layout

## 1. Module Purpose
Defines the visual numbering layout for switch ports (e.g., "Left to Right", "Top to Bottom").

## 2. Key Tables
- **switch_port_numbering_layout** — stores layout names and status.

## 3. Required Relationships
- **switch_port_numbering_layout** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Used by the IDF port visualizer to render port grids correctly.
