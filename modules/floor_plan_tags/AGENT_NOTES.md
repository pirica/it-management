# AGENT_NOTES.md - Floor Plan Tags

## 1. Module Purpose

Flattened CRUD for company-scoped floor-plan tag definitions (`floor_plan_tags.name`). Tags are applied to plans via `floor_plan_item_tags` (junction) or the bespoke `modules/floor_plans/` gallery.

## 2. Key Tables

- **floor_plan_tags** — tag label per company

## 3. Required Relationships

- **floor_plan_tags** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **floor_plan_item_tags** → **floor_plan_tags** (`tag_id`, `ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- `UNIQUE (company_id, name)` — duplicate tag names per tenant are rejected.
- Tenant-scoped; hide `company_id` in UI.
- Soft-delete standard fields; deleted names still occupy UNIQUE until row is gone.

## 5. UI Behavior Requirements

- Departments scaffold CRUD; search/sort/pagination on visible columns.
- No raw FK IDs on list/view when labels exist.

## 6. API / AJAX

- Excel import via `import_excel_rows` on `index.php`.
- CRUD record share (`join.php`).

## 7. Pitfalls

- Tag assignment to a plan is **not** this module — use `floor_plan_item_tags` or `modules/floor_plans/`.
