# AGENT_NOTES.md - VLANs

## 1. Module Purpose
Manages Virtual LAN (VLAN) definitions, including names, IDs, and descriptions.

## 2. Key Tables
- **vlans** — stores VLAN data.

## 3. Required Relationships
- **vlans** → depends on **companies**.
- **vlans** → referenced by **ip_subnets** and **switch_ports**.

## 4. Business Rules (Critical for Agents)
- **Unique ID**: VLAN ID must be unique within a company.

## 12. Module Owner Notes (Optional)
Foundational for network segmentation.
