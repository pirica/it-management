# File Upload Modules

This document lists modules within the IT Management system that support file uploads, along with descriptions of their functionality, storage locations, and Apache hardening rules.

## Overview

Most modules that support file uploads have been upgraded to include a drag-and-drop area (`.itm-photo-upload-target`) for improved user experience, consistent with the `modules/tickets/` module.

Upload and tenant file trees are hardened by `itm_ensure_upload_directory()` and `itm_ensure_upload_directory_chain()` in `includes/bootstrap_helpers.php`. **Do not** call bare `mkdir()` for application upload paths.

## Upload hardening policies

| Policy | Directories | `.htaccess` behaviour | HTTP access |
|--------|-------------|----------------------|-------------|
| `upload` | `images/`, `tickets_photos/`, `floor_plans/` | Disables PHP execution; blocks script extensions | Static files served directly by Apache |
| `deny_http` | `files/` and every segment under `files/{company_id}/…` | `RewriteEngine On` + `RewriteRule ^ - [F]` on **each** folder in the chain | **Denied** — serve through `modules/explorer/file.php` |
| `deny_all` | `backups/` | `Require all denied` | Fully blocked |

### `/files/` chain example

For `files/{company_id}/Private/{username}_{user_id}/private_contacts/`, the system writes managed `.htaccess` (and `index.html`) on:

- `files/`
- `files/{company_id}/`
- `files/{company_id}/Private/`
- `files/{company_id}/Private/{username}_{user_id}/`
- `files/{company_id}/Private/{username}_{user_id}/private_contacts/`

Canonical managed content:

```apache
# ITM files hardening — do not remove (managed by itm_ensure_upload_directory)
RewriteEngine On
RewriteRule ^ - [F]
Options -Indexes -ExecCGI
```

### Helpers (mandatory for new code)

| Helper | When to use |
|--------|-------------|
| `itm_ensure_upload_directory($path, $policy)` | Single directory (bootstrap paths, `images/favicons/`, company floor-plan folder) |
| `itm_ensure_upload_directory_chain($path, $policy, $anchorRoot)` | Ensure `.htaccess` on every segment from anchor to leaf |
| `itm_ensure_files_storage_directory($absolutePath)` | Any path under `files/` — applies `deny_http` from `files/` root |
| `itm_files_serve_url($relativePath)` | Build `../../modules/explorer/file.php?path=…` for UI `<img>` / download links |

Each call to `itm_ensure_upload_directory()` **force-writes** both `.htaccess` (policy body) and an empty `index.html` on that folder — existing files are overwritten, not skipped.

### Is `RewriteRule ^ - [F]` the best approach?

**For `files/` — yes, as the primary control**, combined with:

1. **PHP proxy serving** (`modules/explorer/file.php`) so authorised users still see images/files after direct HTTP is blocked.
2. **Per-segment `.htaccess`** so a malicious upload cannot relax rules in a child folder when parent rules are missing.
3. **Always overwriting** managed `.htaccess` (never “skip if marker exists”) so uploaded `.htaccess` files cannot append RCE directives.
4. **Upload filters** (blocked extensions and dotfiles) in `modules/explorer/api.php`.

**For public asset dirs** (`images/`, `tickets_photos/`, `floor_plans/`) use the `upload` policy instead — those URLs must remain directly servable. `RewriteRule ^ - [F]` alone is insufficient there; the existing `upload` policy disables script execution while allowing images/PDFs.

**Defence in depth:** keep uploads outside the web root where possible, validate MIME/types server-side, and never rely on `.htaccess` when the app may run on nginx or without `AllowOverride`.

## Modules

### 1. Tickets
- **Path:** `modules/tickets/create.php`
- **Storage:** `tickets_photos/` (`upload` policy via `config/config.php`)
- **Description:** Allows uploading multiple photos for ticket records.
- **Implementation:** Uses `itm-photo-upload-target` with drag-and-drop support (via `js/itm-upload-helper.js`).

### 2. Calendar
- **Path:** `modules/calendar/index.php`
- **Description:** Supports importing events from an ICS file.
- **Implementation:** Upgraded to include a drag-and-drop area for `.ics` files (via `js/itm-upload-helper.js`). Works independently of theme initialization.

### 3. Employees
- **Path:** `modules/employees/index.php`
- **Description:** Supports importing employee data from Excel (.xlsx, .xls) or CSV files via a client-side parser.
- **Implementation:** Upgraded to include a drag-and-drop area for import files (via `js/itm-upload-helper.js`).

### 4. Equipment
- **Path:** `modules/equipment/create.php` (and `edit.php` via inclusion)
- **Storage:** `images/` (`upload` policy)
- **Description:** Allows uploading one or more photos during equipment creation or editing.
- **Implementation:** Upgraded to include a drag-and-drop area with photo preview integration and auto-upload on selection during edit (via `js/itm-upload-helper.js`).

### 5. Events
- **Path:** `modules/events/index.php`
- **Description:** Provides functionality to import events from an ICS file.
- **Implementation:** Upgraded to include a drag-and-drop area for `.ics` files (via `js/itm-upload-helper.js`). Logic fixed to avoid redundant listener attachments.

### 6. Patches & Updates
- **Paths:** `modules/patches_updates/create.php`, `modules/patches_updates/edit.php`, `modules/patches_updates/index.php`, `modules/patches_updates/list_all.php`, `modules/patches_updates/view.php`
- **Storage:** `tickets_photos/` (`upload` policy)
- **Description:** Includes photo upload functionality for patch records across various views.
- **Implementation:** All relevant views upgraded to use `itmUploadHelper.setupByClass(".itm-photo-upload-target")` from `js/itm-upload-helper.js`.

### 7. Settings
- **Path:** `modules/settings/index.php`
- **Storage:** `images/favicons/` (`upload` policy per upload)
- **Description:** Allows uploading a favicon image (.ico) and importing database state from a SQL file.
- **Implementation:** Both favicon and SQL import fields upgraded with drag-and-drop areas (via `js/itm-upload-helper.js`). Restored sidebar visibility toggle logic.

### 8. Floor Plans
- **Path:** `modules/floor_plans/create_upload_view.php`, `modules/floor_plans/gallery_helpers.php`
- **Storage:** `floor_plans/{company_id}/` (`upload` policy via `fp_company_upload_dir()`)
- **Description:** Allows uploading Floor Plans (Gallery/AutoCAD/PDF).
- **Implementation:** Upgraded to include a drag-and-drop area (`.itm-photo-upload-target`) for file uploads (via `js/itm-upload-helper.js`).

### 9. Explorer
- **Paths:** `modules/explorer/api.php`, `modules/explorer/setup.php`, `modules/explorer/file.php`
- **Storage:** `files/{company_id}/` tree (`deny_http` on every segment)
- **Description:** General file management.
- **Implementation:** Upgraded to use standard `.itm-photo-upload-target` UI and supports background dropping of files onto the desktop area for automatic uploads. All folder creation uses `itm_ensure_files_storage_directory()` / `explorer_ensure_dir()`.

### 10. Private Contacts
- **Paths:** `modules/private_contacts/create.php`, `modules/private_contacts/edit.php`
- **Storage:** `files/{company_id}/Private/{username}_{user_id}/private_contacts/` (`deny_http` chain)
- **Description:** PNG contact photos.
- **Implementation:** Creates storage via `itm_ensure_files_storage_directory()`; UI serves images through `itm_files_serve_url()` → `modules/explorer/file.php`.

### 11. Notes
- **Path:** `modules/notes/index.php`
- **Storage:** `files/{company_id}/Private/{username}_{user_id}/notes/` (`deny_http` chain)
- **Description:** Image attachments on notes.
- **Implementation:** Creates storage via `itm_ensure_files_storage_directory()`; previews/downloads use `itm_files_serve_url()`.

## Folder creation map (code references)

| Location | Helper / policy |
|----------|-----------------|
| `config/config.php` | `upload` on `images/`, `tickets_photos/`, `floor_plans/`; `deny_all` on `backups/`; `deny_http` on `files/` |
| `modules/explorer/api.php` | `itm_ensure_files_storage_directory()` for all folder operations |
| `modules/explorer/setup.php` | `itm_ensure_files_storage_directory()` |
| `modules/private_contacts/create.php`, `edit.php` | `itm_ensure_files_storage_directory()` |
| `modules/notes/index.php` | `itm_ensure_files_storage_directory()` |
| `modules/floor_plans/gallery_helpers.php` | `itm_ensure_upload_directory($base, 'upload')` |
| `modules/settings/index.php` | `itm_ensure_upload_directory($faviconsDirFs, 'upload')` |
| `modules/equipment/create.php` | `itm_ensure_upload_directory(UPLOAD_PATH, 'upload')` |

## Maintenance script

Backfill `.htaccess` on existing tenant trees:

```bash
php scripts/ensure_files_htaccess_chain.php
```

## Technical Standards

- **Shared Utility:** `js/itm-upload-helper.js` provides centralized drag-and-drop logic.
- **CSS Classes:**
  - `.itm-photo-upload-target`: The primary container for the drag-and-drop area.
  - `.is-dragover`: Applied to the target during drag events to provide visual feedback.
  - `.itm-dropzone-hint`: Used for instructional text within the dropzone.
- **JavaScript:** Implementation involves using `itmUploadHelper.setupById(targetId, inputId)` or `itmUploadHelper.setupByClass(className)`. The helper handles preventing default drag events, toggling visual states, and assigning files to the input while triggering the `change` event.
