# AGENT_NOTES.md - Ticket Priorities

## 1. Module Purpose
Lookup table for ticket priority levels (e.g., "Urgent", "High", "Medium", "Low").

## 2. Key Tables
- **ticket_priorities** — stores priority names and numeric weights.

## 3. Required Relationships
- **ticket_priorities** → depends on **companies**.
- **ticket_priorities** → referenced by **tickets**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Determines the SLA and urgency of support requests.
