# AGENT_NOTES.md - RJ45 Speed

## 1. Module Purpose
Lookup table for network port speeds (e.g., "10/100", "1Gbps", "10Gbps").

## 2. Key Tables
- **rj45_speed** — stores speed descriptions.

## 3. Required Relationships
- **rj45_speed** → depends on **companies**.
- **rj45_speed** → referenced by **switch_ports**, **idf_ports**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Speed description must be unique per company.

## 12. Module Owner Notes (Optional)
Used to document network link capabilities.
