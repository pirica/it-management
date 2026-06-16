# AGENT_NOTES.md - IDF Device Type

## 1. Module Purpose
Lookup table for types of devices found in IDFs (e.g., "Switch", "Patch Panel", "PDU").

## 2. Key Tables
- **idf_device_type** — stores device type names.

## 3. Required Relationships
- **idf_device_type** → depends on **companies**.
- **idf_device_type** → referenced by IDF position/device configurations.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Unique Name**: Must be unique per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Used for IDF rack layout planning.
