# AGENT_NOTES.md - Printer Device Types

## 1. Module Purpose
Lookup table for categories of printers (e.g., "LaserJet", "InkJet", "MFP").

## 2. Key Tables
- **printer_device_types** — stores printer type names.

## 3. Required Relationships
- **printer_device_types** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Tenant scope:** all queries and writes filter by `company_id`.
- **Unique names:** lookup labels must be unique per company where schema enforces uniques.
- **FK safety:** do not delete rows still referenced by child modules — check inbound FKs first.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Used for printer inventory categorization.
