# AGENT_NOTES.md - Bookmarks

## 1. Module Purpose
Hierarchical bookmark manager with private and shared links, folder tree, drag-and-drop, and import/export.

## 2. Key Tables
- **bookmarks** — URL records (`title`, `url`, `shared`, `user_id`, `folder_id`).
- **bookmark_folders** — folder tree (emoji icons in sidebar).

## 3. Required Relationships
- **bookmarks** → **companies**, **users**, **bookmark_folders**.

## 4. Business Rules (Critical for Agents)
- **Privacy:** filter by `user_id` for private bookmarks and `company_id` for shared ones.
- **Visibility:** row visible when `(user_id = logged user OR shared = 1)` and `company_id` matches.
- **Permissions:** shared bookmarks read-only for regular users; admins and creators retain full CRUD.
- **Dual-pane UI:** left folder tree (📁/📂), main list view.
- **Drag-and-drop:** folders reordered/reparented via DnD interactions.
- **Import/export:** browser HTML bookmark files, CSV, and XLSX.
- **Deletion:** single delete may require `bulk_action = 'single_delete'` for shared-handler compatibility.

## 5. UI Behavior Requirements
- Folder tree + list; standard export/import tools where enabled.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.
- Import/export endpoints as implemented in module.

## 8. Multi-Tenant Rules
- `company_id` on all rows; private rows also scoped by `user_id`.

## 10. Common Pitfalls
- SQL ambiguity when joining `bookmark_folders` — alias `active`, `user_id`.
- URLs missing scheme — prepend `http://` or `https://` when saving.

## 12. Module Owner Notes (Optional)
Core productivity feature; folder module: `modules/bookmark_folders/`.
