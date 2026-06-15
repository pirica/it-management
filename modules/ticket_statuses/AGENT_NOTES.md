# AGENT_NOTES.md - Ticket Statuses

## 1. Module Purpose
Lookup table for ticket lifecycle states (e.g., "Open", "In Progress", "Closed", "Archived").

## 2. Key Tables
- **ticket_statuses** — stores status names and active flags.

## 3. Required Relationships
- **ticket_statuses** → depends on **companies**.
- **ticket_statuses** → referenced by **tickets**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Tracks the progress of helpdesk requests.
