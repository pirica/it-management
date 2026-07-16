# AGENT_NOTES.md - Floor Plans

## 1. Module Purpose
Manages physical floor plan documents, including images, PDFs, and AutoCAD files. Supports a hierarchical folder structure and tagging.

## 2. Key Tables
- **floor_plans** — main file metadata. Uses standard audit columns (including `created_by` to track the uploaded by user).
- **floor_plan_folders** — folder hierarchy (`parent_folder_id` self-FK; must match PHP/SQL column name).
- **floor_plan_tags** — available tags.
- **floor_plan_item_tags** — mapping of tags to files. This junction table is company-scoped (`company_id` column) and includes standard lifecycle/audit columns.

## 3. Required Relationships
- **floor_plans** → depends on **companies**.
- **floor_plans** → depends on **floor_plan_folders** (optional).
- **floor_plans** → links to **it_locations** (optional).

## 4. Business Rules (Critical for Agents)
- **File Storage**: Physical files are stored in `floor_plans/{company_id}/`.
- **Folder Moves**: Supports moving files between folders and moving folders themselves (preventing circular moves).
- **Storage Limits**: Standard file size limit is 20MB.

## 5. UI Behavior Requirements
- **Gallery View**: Shows thumbnails for supported image types.
- **Folder Tree**: Interactive sidebar for navigating folders.
- **Drag & Drop**: Supports moving files and folders via drag-and-drop.

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
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
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
