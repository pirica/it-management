# AGENT_NOTES.md - Rack Planner

## 1. Module Purpose
Provides a visual tool for planning and documenting the physical placement of equipment within server racks.

## 2. Key Tables
- Reads from **racks** and **equipment**.
- May use a specific mapping table if implemented (check for `rack_equipment` or similar).

## 3. Required Relationships
- Depends on **companies**.
- Depends on **it_locations**.

## 4. Business Rules (Critical for Agents)
- **Visual Elevation**: Renders a vertical grid representing Rack Units (U).
- **Unit Occupancy**: Each piece of equipment occupies one or more 'U' positions.

## 5. UI Behavior Requirements
- **Visual Drag & Drop**: Often allows moving assets within the rack.
- **Legend**: Color coding based on equipment type.

## 6. API Actions (If Applicable)
- None (Visualization mostly).

## 7. File Structure
- **index.php** — main planner UI.
- **view.php** — detailed rack view.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 12. Module Owner Notes (Optional)
Physical layer management for the data center.
