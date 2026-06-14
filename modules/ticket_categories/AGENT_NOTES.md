# AGENT_NOTES.md - Ticket Categories

## 1. Module Purpose
Lookup table for categorizing support tickets (e.g., "Hardware", "Software", "Network").

## 2. Key Tables
- **ticket_categories** — stores category names.

## 3. Required Relationships
- **ticket_categories** → depends on **companies**.
- **ticket_categories** → referenced by **tickets**.

## 12. Module Owner Notes (Optional)
Used for ticket routing and reporting.
