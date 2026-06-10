# AGENT_NOTES.md - System Access

## 1. Module Purpose
Lookup table for the various systems and applications used within the company.

## 2. Key Tables
- **system_access** — stores system names (e.g., "ERP", "Email").

## 3. Required Relationships
- **system_access** → depends on **companies**.
- **system_access** → referenced by **employee_system_access**.

## 12. Module Owner Notes (Optional)
Defines the list of applications managed for access control.
