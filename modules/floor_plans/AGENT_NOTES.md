# AGENT_NOTES.md - Floor Plans

## 1. Module Purpose
Manages physical floor plan documents, including images, PDFs, and AutoCAD files. Supports a hierarchical folder structure and tagging.

## 2. Key Tables
- **floor_plans** — main file metadata. Uses standard audit columns (including `created_by` to track the uploaded by user). Tenant display-name unique: `UNIQUE (company_id, IFNULL(folder_id,0), display_name)`.
- **floor_plan_folders** — folder hierarchy (`parent_folder_id` self-FK; must match PHP/SQL column name). Tenant folder-name unique: `UNIQUE (company_id, IFNULL(parent_folder_id,0), name)` (sibling names unique under a parent — **unlike** bookmark folders).
- **floor_plan_tags** — available tag labels per company; `UNIQUE (company_id, name)`.
- **floor_plan_item_tags** — many-to-many map of tags to floor plan files. Company-scoped (`company_id`) with standard lifecycle/audit columns.
  - **Identity:** `PRIMARY KEY (floor_plan_id, tag_id)` — one tag link per plan; not a display-name table.
  - **Do not** add `UNIQUE (company_id, floor_plan_id)` (that would allow only one tag per plan).
  - Tenant unique-key audit **skips** this junction (`includes/database_sql_unique_audit.php`).

## 3. Required Relationships
- **floor_plans** → depends on **companies**.
- **floor_plans** → depends on **floor_plan_folders** (optional).
- **floor_plans** → links to **it_locations** (optional).
- **floor_plan_item_tags** → **floor_plans** (`floor_plan_id`), **floor_plan_tags** (`tag_id`), **companies** (`company_id`).

## 4. Business Rules (Critical for Agents)
- **File Storage**: Physical files are stored in `floor_plans/{company_id}/`.
- **Folder Moves**: Supports moving files between folders and moving folders themselves (preventing circular moves).
- **Storage Limits**: Standard file size limit is 20MB.
- **Tagging:** attach/detach tags via `floor_plan_item_tags` only; tag *labels* live on `floor_plan_tags` (those names stay unique per company). Duplicate “names” are not a concern on the junction — rows are `(floor_plan_id, tag_id)` pairs.
- **Unique-key audit:** `php scripts/check_database_sql_company_name_uniques.php` expects name/scope UNIQUEs on `floor_plans` / `floor_plan_folders` / `floor_plan_tags`, and **skips** `floor_plan_item_tags`.

## 5. UI Behavior Requirements
- **View audit meta:** `view_detail.php` renders file metadata plus all six audit columns via `itm_crud_render_view_audit_meta_rows()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.
- **Gallery View**: Shows thumbnails for supported image types.
- **Gallery list header**: `gallery_index_view.php` uses `data-itm-new-button-managed="server"` with centered `sanitize($moduleListHeading)` (🗺️ from sidebar) and Settings `new_button_position` gates for canonical ➕ create/upload link (`title="Create"`, emoji-only visible text).
- **Gallery search**: GET `search` on `index.php` filters via `fp_fetch_gallery_items()` (display name, folder, tag, IT location, file extension); reset control is emoji-only 🔙 on `<a>` and preserves `folder_id` / `unfiled` context when clearing. Gallery and `list_all` search rows use `.itm-floor-plan-search` (`flex-wrap: nowrap`) so Search + 🔙 stay on one line with the input.
- **Folder Tree**: Interactive sidebar for navigating folders.
- **Drag & Drop**: Supports moving files and folders via drag-and-drop.
- **list_all.php**: Flat metadata table (`$crud_action === 'list_all'` in `index.php`) with standard bulk toolbar when `$totalRows >= $perPage` — `bulk-delete-form`, Select to Delete / Cancel / Clear Table, gated row `ids[]` checkboxes, and `bulk-delete-selection.js` in index HTML.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- **index.php** — gallery entry point.
- **view_detail.php** — file preview and detail.
- **gallery_helpers.php** — shared logic for folder/file management.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.
- Directories are company-isolated.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Failed upload disk-store rollback soft-deletes the incomplete `floor_plans` row. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted `floor_plans` / `floor_plan_folders` / `floor_plan_tags` rows still occupy their name/scope UNIQUEs — recreating the same display name under the same parent/folder may collide until purged. `floor_plan_item_tags` has no name UNIQUE (only the plan+tag PK). [Cursor-Valid]
- Do not “fix” the unique audit by adding `UNIQUE (company_id, floor_plan_id)` on `floor_plan_item_tags`. [Cursor-Valid]
- **Schema column name**: `floor_plan_folders.parent_folder_id` is the parent FK (not `parent_folder_name`). Gallery helpers and `index.php` folder create/move handlers use `parent_folder_id`; `database.sql` triggers and unique key must match. [Cursor-Valid]
- **Broken Paths**: Deleting a folder without moving its files can result in "unfiled" records. [Cursor-Valid]
- **Large Files**: Ensure server `post_max_size` and `upload_max_filesize` accommodate larger CAD or PDF files. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (Files in Folder)
```php
$stmt = $conn->prepare("SELECT * FROM floor_plans WHERE company_id = ? AND folder_id = ? AND active = 1");
$stmt->bind_param("ii", $companyId, $folderId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Provides the background images for the Floor Designer.
