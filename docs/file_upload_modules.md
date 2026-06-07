# File Upload Modules

This document lists modules within the IT Management system that support file uploads, along with descriptions of their functionality and implementation details.

## Overview

Most modules that support file uploads have been upgraded to include a drag-and-drop area (`.itm-photo-upload-target`) for improved user experience, consistent with the `modules/tickets/` module.

## Modules

### 1. Tickets
- **Path:** `modules/tickets/create.php`
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
- **Description:** Allows uploading one or more photos during equipment creation or editing.
- **Implementation:** Upgraded to include a drag-and-drop area with photo preview integration and auto-upload on selection during edit (via `js/itm-upload-helper.js`).

### 5. Events
- **Path:** `modules/events/index.php`
- **Description:** Provides functionality to import events from an ICS file.
- **Implementation:** Upgraded to include a drag-and-drop area for `.ics` files (via `js/itm-upload-helper.js`). Logic fixed to avoid redundant listener attachments.

### 6. Patches & Updates
- **Paths:** `modules/patches_updates/create.php`, `modules/patches_updates/edit.php`, `modules/patches_updates/index.php`, `modules/patches_updates/list_all.php`, `modules/patches_updates/view.php`
- **Description:** Includes photo upload functionality for patch records across various views.
- **Implementation:** All relevant views upgraded to use `itmUploadHelper.setupByClass(".itm-photo-upload-target")` from `js/itm-upload-helper.js`.

### 7. Settings
- **Path:** `modules/settings/index.php`
- **Description:** Allows uploading a favicon image (.ico) and importing database state from a SQL file.
- **Implementation:** Both favicon and SQL import fields upgraded with drag-and-drop areas (via `js/itm-upload-helper.js`). Restored sidebar visibility toggle logic.

### 8. Floor Plans
- **Path:** `modules/floor_plans/create_upload_view.php`
- **Description:** Allows uploading Floor Plans (Gallery/AutoCAD/PDF).
- **Implementation:** Upgraded to include a drag-and-drop area (`.itm-photo-upload-target`) for file uploads (via `js/itm-upload-helper.js`).

### 9. Explorer
- **Path:** `modules/explorer/index.php`
- **Description:** General file management.
- **Implementation:** Upgraded to use standard `.itm-photo-upload-target` UI and supports background dropping of files onto the desktop area for automatic uploads.

## Technical Standards

- **Shared Utility:** `js/itm-upload-helper.js` provides centralized drag-and-drop logic.
- **CSS Classes:**
  - `.itm-photo-upload-target`: The primary container for the drag-and-drop area.
  - `.is-dragover`: Applied to the target during drag events to provide visual feedback.
  - `.itm-dropzone-hint`: Used for instructional text within the dropzone.
- **JavaScript:** Implementation involves using `itmUploadHelper.setupById(targetId, inputId)` or `itmUploadHelper.setupByClass(className)`. The helper handles preventing default drag events, toggling visual states, and assigning files to the input while triggering the `change` event.
