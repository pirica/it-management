# AGENT_NOTES.md - Ticket Priorities

## 1. Module Purpose
Lookup table for ticket priority levels (e.g., "Urgent", "High", "Medium", "Low").

## 2. Key Tables
- **ticket_priorities** — stores priority names and numeric weights.

## 3. Required Relationships
- **ticket_priorities** → depends on **companies**.
- **ticket_priorities** → referenced by **tickets**.

## 12. Module Owner Notes (Optional)
Determines the SLA and urgency of support requests.
