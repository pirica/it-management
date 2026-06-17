# AGENT_NOTES.md - Rack Planner

## 1. Module Purpose
Visual rack elevation planner. Stores layout JSON per named rack plan and references devices from catalogs, equipment, and unlinked IDF positions.

## 2. Key Tables
- **rack_planner** — `name`, `rack_units`, `layout_json`, `notes` (primary persistence).
- **Reads** (not owned): **catalogs**, **equipment**, **idf_positions**, **racks**, **it_locations**.

## 3. Required Relationships
- **rack_planner** → **companies**.
- Layout device codes: `catalog:<id>`, `equipment:<id>`, `idf_unlinked:<token>`.

## 4. Business Rules (Critical for Agents)
- **Price source sync (mandatory):** on save/autosave, price edits must persist to source tables:
  - `catalog:<id>` → `catalogs.price`
  - `equipment:<id>` → `equipment.purchase_cost`
  - `idf_unlinked:<token>` → `idf_positions.price` (token-style `equipment_id` `^[0-9]{4}-[0-9]{4}$`)
- Do not keep price changes only inside `layout_json`.
- **Tier D** bespoke module — navigation smoke in module browser QA; not standard flattened CRUD.

## 5. UI Behavior Requirements
- Vertical rack-unit grid; drag/drop placement.
- Custom handlers in `includes/handlers.php` — disable redundant default exports when custom layout applies.

## 7. File Structure
- `index.php` — main planner UI.
- `includes/bootstrap.php`, `functions.php`, `handlers.php`, `partials/render.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; unique rack plan name per company (`rack_planner_name_company`).

## 10. Common Pitfalls
- There is no `rack_equipment` mapping table — layout lives in `layout_json`.
- Partial price sync breaks catalog/equipment/IDF reporting — always update source row.

## 12. Module Owner Notes (Optional)
See `modules/rack_planner/includes/AGENT_NOTES.md` for partial/handler details.
