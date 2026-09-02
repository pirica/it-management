# AGENT_NOTES.md - VLANs Per Port

## 1. Module Purpose
Per-switch-port VLAN assignment rows: module, port number, enabled state, type, VLAN, allowed VLAN list, and access policy.

## 2. Key Tables
- **vlans_per_port** — main CRUD table.
- Lookup tables: **vlans_per_port_modules** (Built-in), **vlans_per_port_types** (Access), **vlans_per_port_access_policies** (Open).

## 3. Required Relationships
- **vlans_per_port** → **companies**, **vlans** (`vlan_id`), lookup FKs above.

## 4. Business Rules (Critical for Agents)
- Create defaults via `includes/itm_vlan_lookup_defaults.php`: Built-in module, Access type, Open access policy, `active=1`.
- `allowed_vlans` is free-text (CSV or range notation as entered by operators).

## 5. UI Behavior Requirements
- List column order: module, port, active, type, vlan, allowed VLANs, access policy.
- Standard scaffold CRUD with soft-delete.

## 6. API Actions (If Applicable)
- **import_excel_rows** on `index.php`.

## 7. File Structure
- Standard CRUD entry files under `modules/vlans_per_port/`.

## 8. Multi-Tenant Rules
- Scope by `company_id`; hide `company_id` in UI.

## 9. Audit Logging Requirements
- `trg_vlans_per_port_audit_*` on parent table; lookup tables have matching `trg_*` triggers.

## 10. Common Pitfalls
- Ensure lookup seeds exist for the tenant before creating rows (replicate block in `db/02_data.sql`).

## 11. Examples of Safe Code Patterns
```php
$stmt = $conn->prepare('SELECT * FROM vlans_per_port WHERE company_id = ? AND deleted_at IS NULL');
```

## 12. Module Owner Notes (Optional)
Complements core `modules/vlans/` definitions.
