# AGENT_NOTES.md - VLAN VPN Mode

## 1. Module Purpose
Tenant lookup for VLAN **VPN mode** (`Enabled`, `Disabled`; default **Enabled**).

## 2. Key Tables
- **vlans_vpn_mode**

## 3. Required Relationships
- Referenced by **vlans.vpn_mode_id**.

## 4. Business Rules (Critical for Agents)
- Seeds: Enabled + Disabled for each company; create default **Enabled**.

## 5–12. Module contract
Flattened lookup CRUD; `trg_vlans_vpn_mode_audit_*`.
