# AGENT_NOTES.md - IDF Positions

## 1. Module Purpose
Defines the position of a device (e.g., U1, U2) within an IDF rack.

## 2. Key Tables
- **idf_positions** — stores device placement in racks.

## 3. Required Relationships
- **idf_positions** → depends on **companies**.
- **idf_positions** → depends on **idfs**.
- **idf_positions** → depends on **idf_device_type**.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **No Overlap**: Multiple devices should not occupy the same rack unit (logic enforced in UI/validation).

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Rack Elevation**: Used to render a visual rack elevation view.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Physical layout planning for networking racks.
