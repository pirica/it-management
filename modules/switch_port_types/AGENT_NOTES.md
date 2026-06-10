# AGENT_NOTES.md - Switch Port Types

## 1. Module Purpose
Lookup table for types of switch ports (e.g., "RJ45", "SFP", "Door", "Access Point").

## 2. Key Tables
- **switch_port_types** — stores port type names.

## 3. Required Relationships
- **switch_port_types** → depends on **companies**.
- **switch_port_types** → referenced by **switch_ports** and **floor_designer_points**.

## 4. Business Rules (Critical for Agents)
- **Name-Based Fallback**: The UI often uses name-based matching (e.g., 'RJ45') as a fallback if IDs don't match across companies.

## 12. Module Owner Notes (Optional)
Core categorization for network ports.
