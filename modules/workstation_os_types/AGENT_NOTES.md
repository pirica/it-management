# AGENT_NOTES.md - Workstation Os Types

## 1. Module Purpose
Lookup table for workstation Os Types (e.g., specific to workstation configurations and asset management).

## 2. Key Tables
- **workstation_os_types** — stores os_types names and status.

## 3. Required Relationships
- **workstation_os_types** → depends on **companies**.
- Referenced by workstation asset management modules.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique within a `company_id`.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Categorizes workstation-specific hardware and software configurations.
