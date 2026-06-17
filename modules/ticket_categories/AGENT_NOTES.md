# AGENT_NOTES.md - Ticket Categories

## 1. Module Purpose
Lookup table for categorizing support tickets (e.g., "Hardware", "Software", "Network").

## 2. Key Tables
- **ticket_categories** — stores category names.

## 3. Required Relationships
- **ticket_categories** → depends on **companies**.
- **ticket_categories** → referenced by **tickets**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Used for ticket routing and reporting.
