# AGENT_NOTES.md - rack_planner/includes

## 1. Module Purpose
Bootstrap, handlers, and view functions for the Rack Planner bespoke module (Tier D QA smoke).

## 4. Business Rules (Critical for Agents)
- **Rack Planner price source sync:** price edits for `catalog:`, `equipment:`, and `idf_unlinked:` devices must persist to `catalogs.price`, `equipment.purchase_cost`, or `idf_positions.price` — not only `layout_json`.
- Layout stored in `rack_planner.layout_json` must stay consistent with source tables.

## 7. File Structure
- `bootstrap.php` — module init.
- `functions.php` — layout/price helpers.
- `handlers.php` — POST/save/autosave handlers.
- **partials/** — `render.php` and UI fragments.

## 10. Common Pitfalls

- Price edits for `catalog:`, `equipment:`, and `idf_unlinked:` devices must update source tables (`catalogs.price`, `equipment.purchase_cost`, `idf_positions.price`) — not only `layout_json`. [Cursor-Valid]
- Keep POST/autosave handlers CSRF-protected and tenant-scoped; prefer shared JSON response helpers used by other AJAX modules. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Tier D module — module browser QA runs navigation smoke only. Parent: `modules/rack_planner/AGENT_NOTES.md`.
