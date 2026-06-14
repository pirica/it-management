# AGENT_NOTES.md - Switch Status

## 1. Module Purpose
Lookup table for the status of a switch port (e.g., "Up", "Down", "Disabled").

## 2. Key Tables
- **switch_status** — stores status names.

## 3. Required Relationships
- **switch_status** → depends on **companies**.
- **switch_status** → referenced by **switch_ports**.

## 12. Module Owner Notes (Optional)
Indicates the operational state of network links.
