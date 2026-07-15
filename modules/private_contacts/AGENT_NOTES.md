# AGENT_NOTES.md - Private Contacts

## 1. Module Purpose
Per-user private address book (not the shared company Contacts module). Stores personal contacts with photos, favourites, labels, and organisation fields.

## 2. Key Tables
- **private_contacts** — contact records scoped by `employee_id` and `company_id`.

## 3. Required Relationships
- **private_contacts** → depends on **companies**, **employees**.
- Photos stored under `files/{company_id}/Private/{username}_{employee_id}/private_contacts/`.

## 4. Business Rules (Critical for Agents)
- **Strict user isolation:** all queries must include `employee_id = logged-in employee`. Never show another user's private contacts.
- Favourite toggle and delete are POST + CSRF (`index_logic.php`).
- Distinct from `modules/contacts/` (company directory).

## 5. UI Behavior Requirements
- Custom list with search, favourite star (AJAX), photo thumbnails.
- **Search:** matches first/last name, email, organisation, phone (`phone1_value`), labels CSV, and full name concat — aligned with visible list columns.
- `data-itm-db-import-endpoint` on index table for Excel import.
- Actions column uses `itm-actions-cell` markers.
- Create/edit profile photo uses the employees-style upload UI (`includes/profile_photo_fields.php`: circular drag-and-drop target, `itm-upload-helper.js`, **PNG only**). Hint: `Drag and drop or click to upload PNG.` Do not nest `<label for>` inside a click-bound upload target without the shared label guard in `itm-upload-helper.js` (prevents double file-picker).

## 6. API Actions (If Applicable)
- **toggle_favourite** (POST on `index_logic.php`) — CSRF + `employee_id` scope; AJAX star toggle on list.
- **import_excel_rows** (JSON POST on `index.php`) — bulk import with `data-itm-db-import-endpoint="index.php"`.

## 7. File Structure
- `index.php` — HTML list view.
- `index_logic.php` — auth, POST handlers, contact query.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD screens.
- `edit_form.php` — shared form sections for create/edit.
- `includes/profile_photo_fields.php` — employees-matching photo UI for create/edit.
- `includes/private_contact_photo.php` — photo URL + upload store helpers.

## 8. Multi-Tenant Rules
- `company_id` plus **mandatory** `employee_id` filter on every SELECT/UPDATE/DELETE.

## 9. Audit Logging Requirements
- **Private data (no audit):** `private_contacts` is exempt from `audit_logs` and database audit triggers per `AGENTS.md` → **Private data — no audit trail**. Do not add PHP audit hooks for contact mutations.

## 10. Common Pitfalls
- Do not reuse company contacts visibility rules — this module is user-private only. [Valid]-[2026-07-15]
- Photo paths must stay inside the user's Private explorer segment. [Valid]-[2026-07-15]
- Do not drop `employee_id` from DELETE/WHERE clauses. [Valid]-[2026-07-15]
- Profile photo upload: use `includes/profile_photo_fields.php` + `pc_contact_photo_store_upload()`; **PNG only** (unlike employees, which accepts PNG and JPG). Type resolution uses `pc_contact_photo_resolve_png_extension()`. Upload failures surface `photo_error` on create/edit redirects. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM private_contacts WHERE employee_id = ? AND company_id = ?");
$stmt->bind_param("ii", $employeeId, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
standard CRUD fixes allowed, but preserve per-user privacy on every code path.
