# AGENT_NOTES.md - Racks

## 1. Module Purpose
Manages physical server racks, including their size (total units), location, and manufacturer.

## 2. Key Tables
- **racks** — main rack storage.

## 3. Required Relationships
- **racks** → depends on **companies**.
- **racks** → depends on **it_locations**.
- **racks** → depends on **rack_statuses**.
- **racks** → depends on **manufacturers**.

## 4. Business Rules (Critical for Agents)
- **Total Units**: Tracks the capacity of the rack in 'U' (e.g., 42U).

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
The physical enclosure for server infrastructure.
