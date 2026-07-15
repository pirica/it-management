# AGENT_NOTES.md - Private Contacts Includes

## 1. Module Purpose
Provides shared form widgets and file storage utilities specifically for managing the upload and rendering of private contact profile photos.

## 2. Key Tables
- **private_contacts** — own table where the contact `photo` basename is saved.

## 3. Required Relationships
- Saved photos are stored on-disk under the employee's private explorer segment: `files/{company_id}/Private/{username}_{employee_id}/private_contacts/`.

## 4. Business Rules (Critical for Agents)
- **PNG Only constraint**: Unlike the general Employees module which accepts JPEG and PNG, the Private Contacts photo uploader strictly enforces **PNG files only**.
- **Private Isolation**: Photo file reads and writes must be fully scoped to the active employee's private folder to prevent cross-user data leakage.

## 5. UI Behavior Requirements
- **profile_photo_fields.php** — Renders a styled, circular drag-and-drop dropzone using `js/itm-upload-helper.js` targeting `.itm-employee-photo-target`.
- Serves photos securely through `itm_files_serve_url()`.

## 6. API Actions (If Applicable)
- None.

## 7. File Structure
- **private_contact_photo.php** — Photo store, resolve, extension validation, and unlink helpers.
- **profile_photo_fields.php** — Form layout representing the upload container widget.
- **index.html** — Directory listing prevention.

## 8. Multi-Tenant Rules
- Disk storage hierarchies are partitioned by `$company_id` and `$employee_id`.

## 9. Audit Logging Requirements
- Adding or changing photos triggers private contact update audits.

## 10. Common Pitfalls
- Hand-building direct relative HTTP links to the upload files on-disk—these are blocked by the `.htaccess` `deny_http` rule. Always route them through `itm_files_serve_url()`.
