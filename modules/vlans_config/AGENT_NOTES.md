# AGENT_NOTES.md - VLAN Config

## 1. Module Purpose
Tenant lookup for VLAN **Config** mode (`Manual`, `Auto`). Default seed per company: **Manual**.

## 2. Key Tables
- **vlans_config** — `name` label per `company_id`.

## 3. Required Relationships
- **vlans_config** → **companies**.
- Referenced by **vlans.config_id** (`ON DELETE SET NULL`).

## 4. Business Rules (Critical for Agents)
- Unique `(company_id, name)`.
- Default on new VLAN create: **Manual** via `includes/itm_vlan_lookup_defaults.php`.

## 5. UI Behavior Requirements
- Standard flattened CRUD lookup module (soft-delete scaffold).
- Hide `company_id` from UI.

## 6. API Actions (If Applicable)
- **import_excel_rows** on `index.php`.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scope by `company_id`.

## 9. Audit Logging Requirements
- `trg_vlans_config_audit_*` in `db/03_triggers.sql`.

## 10. Common Pitfalls
- Do not delete rows referenced by **vlans** for the active tenant without reassigning FKs.

## 11. Examples of Safe Code Patterns
See `modules/warranty_types/AGENT_NOTES.md` (same lookup pattern).

## 12. Module Owner Notes (Optional)
Paired with `modules/vlans/` config dropdown.
