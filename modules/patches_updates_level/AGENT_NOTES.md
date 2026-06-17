# AGENT_NOTES.md - Patches & Updates Level

## 1. Module Purpose
Lookup table for severity or priority levels of patches (e.g., "Critical", "Recommended").

## 2. Key Tables
- **patches_updates_level** — stores level names.

## 3. Required Relationships
- **patches_updates_level** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Level name must be unique per company.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Used to prioritize patching tasks.
