# AGENT_NOTES.md - JavaScript

## 1. Module Purpose
Contains the frontend logic for the IT Management system, including UI helpers, file upload management, and table interactivity.

## 4. Business Rules (Critical for Agents)
- **Zero Dependencies**: The system avoids NPM packages; libraries are included as vendor scripts.
- **CSRF Token**: Must be included in all async POST requests (usually available via `window.ITM_CSRF_TOKEN`).

## 7. File Structure
- **itm-upload-helper.js** — shared utility for drag-and-drop file uploads.
- **table-tools.js** — handles Excel imports and list view interactivity.
- **select-add-option.js** — supports inline addition of parent records from dropdowns.
- **theme.js** — light/dark mode via `document.documentElement` `data-theme` and `localStorage.theme`. Prefers `window.ITM_PREFERRED_THEME` when set (from `employees.theme` / `$_SESSION['ui_theme']` via profile/`login.php`/`includes/header.php`).
- **vendor/** — contains third-party libraries like `xlsx.full.min.js`.

## 10. Common Pitfalls
- Attaching redundant event listeners in loops. [Cursor-Fixed]
- Loading utility scripts after the blocks that depend on them. [Cursor-Fixed]
- **Upload targets with inner `<label for="fileInput">`:** `itm-upload-helper.js` skips programmatic `fileInput.click()` when the click originated on the associated label — otherwise the native label activation plus the target click handler opens the file picker twice. [Cursor-Fixed]
  - *Robust Check:* Uses standard DOM APIs (`label.control`, `label.contains`, `fileInput.labels`, and `label.htmlFor === fileInput.id`) to reliably skip programmatic clicks on all associated label click events. [Cursor-Valid]
- Theme appearing stuck on Light after profile save: prefer server `ITM_PREFERRED_THEME` over stale `localStorage`, and avoid hardcoded light hex colors on dashboard pages. [Cursor-Fixed]

## 12. Module Owner Notes (Optional)
Essential for the "modern" interactive feel of the legacy PHP architecture.
