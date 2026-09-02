# AGENT_NOTES.md - VLAN Uplink

## 1. Module Purpose
Tenant lookup for VLAN **Uplink** (`Any` default seed per company).

## 2. Key Tables
- **vlans_uplink**

## 3. Required Relationships
- **vlans_uplink** → **companies**; referenced by **vlans.uplink_id**.

## 4. Business Rules (Critical for Agents)
- Unique `(company_id, name)`; default **Any** on VLAN create.

## 5–12. Module contract
Same flattened lookup CRUD pattern as `modules/vlans_config/`; audit triggers `trg_vlans_uplink_audit_*`.
