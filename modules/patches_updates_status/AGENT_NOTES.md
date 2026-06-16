# AGENT_NOTES.md - Patches & Updates Status

## 1. Module Purpose
Lookup table for the status of a patch installation (e.g., "Pending", "Installed", "Failed").

## 2. Key Tables
- **patches_updates_status** — stores status names.

## 3. Required Relationships
- **patches_updates_status** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Status name must be unique per company.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Tracks progress of software updates.
