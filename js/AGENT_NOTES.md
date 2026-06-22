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
- **vendor/** — contains third-party libraries like `xlsx.full.min.js`.

## 10. Common Pitfalls
- Attaching redundant event listeners in loops.
- Loading utility scripts after the blocks that depend on them.
- **Upload targets with inner `<label for="fileInput">`:** `itm-upload-helper.js` skips programmatic `fileInput.click()` when the click originated on the associated label — otherwise the native label activation plus the target click handler opens the file picker twice.

## 12. Module Owner Notes (Optional)
Essential for the "modern" interactive feel of the legacy PHP architecture.
