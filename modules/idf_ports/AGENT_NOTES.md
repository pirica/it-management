# AGENT_NOTES.md - IDF Ports

## 1. Module Purpose
Manages individual ports on IDF devices (switches, patch panels).

## 2. Key Tables
- **idf_ports** — stores port configuration and status.

## 3. Required Relationships
- **idf_ports** → depends on **companies**.
- **idf_ports** → depends on **idf_positions** (the device).
- **idf_ports** → links to **vlans**, **rj45_speed**, **poe**, etc.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Port Numbering**: Ports are typically numbered sequentially within a device.
- **Consistency**: Status and speed should match the physical configuration.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Visualizer**: Often represented in a grid or visual port layout.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
The granular level of IDF management.
