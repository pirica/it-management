# File Upload Modules

This document lists modules within the IT Management system that support file uploads, along with descriptions of their functionality, storage locations, and Apache hardening rules.

## Overview

Most modules that support file uploads have been upgraded to include a drag-and-drop area (`.itm-photo-upload-target`) for improved user experience, consistent with the `modules/tickets/` module.

Upload and tenant file trees are hardened by `itm_ensure_upload_directory()` and `itm_ensure_upload_directory_chain()` in `includes/bootstrap_helpers.php`. **Do not** call bare `mkdir()` for application upload paths.

## Force-create contract (mandatory)

Every `itm_ensure_upload_directory()` call — including each segment walked by `itm_ensure_upload_directory_chain()` — **must force-create** two managed files on that folder:

| File | Behaviour |
|------|-----------|
| **`.htaccess`** | Always **overwritten** with the canonical policy body for that directory (`upload`, `deny_http`, or `deny_all`). Never skip when a file already exists or contains an ITM marker. |
| **`index.html`** | Always **overwritten** with an empty placeholder from `itm_upload_directory_empty_index_html()`. Applies to **all** policies (including `backups/`). |

Success requires all three to exist: the directory, `.htaccess`, and `index.html`.

Empty `index.html` content (managed — do not edit by hand):

```html
<!DOCTYPE html><html><head><title></title></head><body></body></html>
```

**Every folder** in the project (every directory under the repository root, not only upload trees) **must** have an empty `index.html`. Upload paths also receive managed `.htaccess` via `itm_ensure_upload_directory()`. Missing placeholders are a directory-listing risk; deleted placeholders must be restored on the next ensure or backfill run.

**Do not** create upload folders with bare `mkdir()` and add `.htaccess` / `index.html` manually in a follow-up step — call the helper once so both files are written atomically for that path.

## Upload hardening policies

| Policy | Directories | `.htaccess` (force-written) | `index.html` (force-written) | HTTP access |
|--------|-------------|----------------------------|------------------------------|-------------|
| `upload` | `images/`, `tickets_photos/`, `floor_plans/` | Disables PHP execution; blocks script extensions | Empty placeholder | Static files served directly by Apache |
| `deny_http` | `files/` and every segment under `files/{company_id}/…` | `RewriteEngine On` + `RewriteRule ^ - [F]` on **each** folder in the chain | Empty placeholder | **Denied** — serve through `modules/explorer/file.php` |
| `deny_all` | `backups/` | `Require all denied` | Empty placeholder | Fully blocked |

### `/files/` chain example

For `files/{company_id}/Private/{username}_{user_id}/private_contacts/`, the system **force-creates** managed `.htaccess` and empty `index.html` on:

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
| `itm_ensure_upload_directory($path, $policy)` | Single directory — force-writes `.htaccess` + empty `index.html` |
| `itm_ensure_upload_directory_chain($path, $policy, $anchorRoot)` | Walk anchor→leaf; force-writes `.htaccess` + empty `index.html` on **every** segment |
| `itm_ensure_files_storage_directory($absolutePath)` | Any path under `files/` — `deny_http` chain from `files/` root |
| `itm_files_serve_url($relativePath)` | Build `../../modules/explorer/file.php?path=…` for UI `<img>` / download links |
| `itm_upload_directory_empty_index_html()` | Canonical empty `index.html` body (used internally; do not duplicate) |

### Is `RewriteRule ^ - [F]` the best approach?

**For `files/` — yes, as the primary control**, combined with:

1. **PHP proxy serving** (`modules/explorer/file.php`) so authorised users still see images/files after direct HTTP is blocked.
2. **Per-segment `.htaccess`** so a malicious upload cannot relax rules in a child folder when parent rules are missing.
3. **Force-overwriting** managed `.htaccess` and empty `index.html` on every ensure (never “skip if exists”) so uploaded `.htaccess` files cannot append RCE directives and deleted `index.html` files are restored.
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

| Location | Helper / policy | Force-created files per folder |
|----------|-----------------|--------------------------------|
| `config/config.php` | `upload` on `images/`, `tickets_photos/`, `floor_plans/`; `deny_all` on `backups/`; `deny_http` on `files/` | `.htaccess` + empty `index.html` |
| `modules/explorer/api.php` | `itm_ensure_files_storage_directory()` for all folder operations | `.htaccess` + empty `index.html` on each chain segment |
| `modules/explorer/setup.php` | `itm_ensure_files_storage_directory()` | `.htaccess` + empty `index.html` on each chain segment |
| `modules/private_contacts/create.php`, `edit.php` | `itm_ensure_files_storage_directory()` | `.htaccess` + empty `index.html` on each chain segment |
| `modules/notes/index.php` | `itm_ensure_files_storage_directory()` | `.htaccess` + empty `index.html` on each chain segment |
| `modules/floor_plans/gallery_helpers.php` | `itm_ensure_upload_directory($base, 'upload')` | `.htaccess` + empty `index.html` |
| `modules/settings/index.php` | `itm_ensure_upload_directory($faviconsDirFs, 'upload')` | `.htaccess` + empty `index.html` |
| `modules/equipment/create.php` | `itm_ensure_upload_directory(UPLOAD_PATH, 'upload')` | `.htaccess` + empty `index.html` |

## Maintenance scripts

| Script | Scope | What it force-writes |
|--------|-------|----------------------|
| `php scripts/empty_folders.php` | **Entire project** (every folder under repo root; skips `.git`, `.github`, and other dot dirs) | Empty `index.html` on **every** folder; managed `.htaccess` + `index.html` on upload paths (`images/`, `tickets_photos/`, `floor_plans/`, `backups/`, `files/`) |
| `php scripts/ensure_files_htaccess_chain.php` | `files/` only | `deny_http` `.htaccess` + empty `index.html` on every segment (idempotent) |

Run `empty_folders.php` after deploy, when adding new directories, or when folders were created without placeholders. The script lists only **new or changed** paths (repo-relative `index.html`) before the summary line. A second run on an unchanged tree prints `No new or changed folders.` and reports how many folders were already current.

```bash
php scripts/empty_folders.php
```

Example output (first run after adding folders):

```
Scanning project folders for missing or outdated index.html...

modules/new_module1/index.html
modules/new_module2/index.html
[PASS] Updated 2 folder(s) under /path/to/it-management (0 upload-hardened). 249 already current (251 scanned).
```

Example output (subsequent run — nothing to do):

```
Scanning project folders for missing or outdated index.html...

No new or changed folders.
[PASS] Updated 0 folder(s) under /path/to/it-management (0 upload-hardened). 251 already current (251 scanned).
```

`files/` only (faster when other roots are already correct):

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
