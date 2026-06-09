# AGENT_NOTES.md - Ticket Statuses

## 1. Module Purpose
Lookup table for ticket lifecycle states (e.g., "Open", "In Progress", "Closed", "Archived").

## 2. Key Tables
- **ticket_statuses** — stores status names and active flags.

## 3. Required Relationships
- **ticket_statuses** → depends on **companies**.
- **ticket_statuses** → referenced by **tickets**.

## 12. Module Owner Notes (Optional)
Tracks the progress of helpdesk requests.
