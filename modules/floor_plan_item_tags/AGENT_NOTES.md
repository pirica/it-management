# AGENT_NOTES.md - Floor Plan Item Tags

## 1. Module Purpose

Flattened CRUD for the `floor_plan_item_tags` junction (which tags are linked to which `floor_plans` rows). Composite primary key `(floor_plan_id, tag_id)`.

## 2. Key Tables

- **floor_plan_item_tags** — plan ↔ tag links (includes `company_id` for tenancy)

## 3. Required Relationships

- **floor_plan_item_tags** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **floor_plan_item_tags** → **floor_plans** (`floor_plan_id`, `ON DELETE CASCADE`)
- **floor_plan_item_tags** → **floor_plan_tags** (`tag_id`, `ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- No surrogate `id` column — PK is `(floor_plan_id, tag_id)`; scaffold list uses both columns.
- Tenant scope via `company_id`; hide in UI.
- Unique-key audit **skips** this table (no name UNIQUE — only composite PK).

## 5. UI Behavior Requirements

- List/view must show **floor plan** and **tag** labels for `floor_plan_id` / `tag_id`.
- Standard bulk delete, search, pagination when row count ≥ `records_per_page`.

## 6. API / AJAX

- `import_excel_rows` on `index.php`.
- CRUD record share slug registered like other scaffold modules.

## 7. Pitfalls

- Prefer gallery tag UI in `modules/floor_plans/` for end users; this module is maintenance/backfill.
