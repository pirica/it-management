# AGENT_NOTES.md - Floor Plan Folders

## 1. Module Purpose

Flattened CRUD for the `floor_plan_folders` hierarchy (parent/child folder names per company). Primary gallery UX remains in `modules/floor_plans/`; this module is for direct admin maintenance, imports, and registry/sidebar discovery.

## 2. Key Tables

- **floor_plan_folders** — folder tree for floor-plan assets (`parent_folder_id` self-FK)

## 3. Required Relationships

- **floor_plan_folders** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **floor_plan_folders** → self (`parent_folder_id`, `ON DELETE CASCADE`)
- **floor_plans** → **floor_plan_folders** (`folder_id`, optional)

## 4. Business Rules (Critical for Agents)

- Tenant scope: all queries/inserts use session `company_id`; hide `company_id` in UI.
- Sibling folder names are unique per company under the same parent (`UNIQUE (company_id, IFNULL(parent_folder_id,0), name)`).
- Soft-deleted rows keep UNIQUE slots until purged — same as `modules/floor_plans/`.
- Standard scaffold soft-delete / audit columns apply (`active`, `deleted_*`, `created_*`, `updated_*`).

## 5. UI Behavior Requirements

- Flattened CRUD (`index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) from departments scaffold.
- List FK `parent_folder_id` must show folder **name**, not raw ID.
- `company_id` hidden on list/view/forms.

## 6. API / AJAX

- `index.php` `import_excel_rows` JSON endpoint for table-tools import.
- Record share: `join.php` + `create_share_session` (CRUD record share capable slug).

## 7. Pitfalls

- Column is `parent_folder_id`, not `parent_id` or `parent_folder_name`.
- Do not confuse with `bookmark_folders` — different module and uniqueness rules.
