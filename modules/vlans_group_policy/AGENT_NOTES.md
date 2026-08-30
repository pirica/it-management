# AGENT_NOTES.md - VLAN Group Policy

## 1. Module Purpose
Tenant lookup for VLAN **Group policy** (`None` default per company).

## 2. Key Tables
- **vlans_group_policy**

## 3. Required Relationships
- Referenced by **vlans.group_policy_id**.

## 4. Business Rules (Critical for Agents)
- Default **None** on VLAN create (`includes/itm_vlan_lookup_defaults.php`).

## 5–12. Module contract
Flattened lookup CRUD; `trg_vlans_group_policy_audit_*`.
