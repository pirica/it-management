# AGENT_NOTES.md - Private Contacts

## 1. Module Purpose
Per-user private address book (not the shared company Contacts module). Stores personal contacts with photos, favourites, labels, and organisation fields.

## 2. Key Tables
- **private_contacts** — contact records scoped by `employee_id` and `company_id`. PII text columns are `TEXT` in `db/03_triggers.sql` so vault ciphertext fits (re-import or widen legacy `varchar` columns before enabling encryption on existing DBs).
- **private_contact_share_sessions** — temporary QR / 6-digit share snapshots (`payload_json` plaintext until expiry; private-data exempt from audit triggers).

## 3. Required Relationships
- **private_contacts** → depends on **companies**, **employees**.
- Photos stored under `files/{company_id}/Private/{username}_{employee_id}/private_contacts/`.

## 4. Business Rules (Critical for Agents)
- **Strict user isolation:** all queries must include `employee_id = logged-in employee`. Never show another user's private contacts.
- **Vault (mandatory):** all contact PII text fields encrypt at rest with `itm_encrypt()` / `$_SESSION['vault_key']` (same master key as Passwords/Notes). List/create/edit/view show a lock screen until the vault is unlocked (`pc_vault_bootstrap.php` → `includes/itm_vault_unlock.php`; optional TOTP when enabled). Legacy plaintext rows still decrypt via `pc_private_text_legacy_plaintext_check()`.
- **Search/sort/pagination:** list loads rows for the employee, hydrates/decrypts, then filters and paginates in PHP (`pc_row_matches_search()`, `pc_compare_contact_rows()`) — no SQL `LIKE` on ciphertext.
- Favourite toggle and delete are POST + CSRF (`index_logic.php`).
- Distinct from `modules/contacts/` (company directory).

## 5. UI Behavior Requirements
- **View audit meta:** Detail view renders all six scaffold audit columns via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). Row meta is for soft-delete display only; this module stays **private-data exempt** from `audit_logs` triggers.
- Custom list with search, sort, pagination, favourite star (AJAX), photo thumbnails.
- **List header:** `data-itm-new-button-managed="server"` with centered `$moduleListHeading` from `itm_sidebar_label_for_module()` and Settings `new_button_position` create slots (`itm-list-new-button`).
- **Search:** server-side GET `search` on `index.php` (`$searchRaw`) — after vault hydrate, `pc_row_matches_search()` filters on first/last name, email, organisation, phone, labels, and full name (no SQL `LIKE` on ciphertext). Sort via `pc_compare_contact_rows()`; favourites stay first.
- **Pagination:** `itm_resolve_records_per_page()`, `$perPage`, `$totalRows`, `LIMIT`/`$offset`, Previous/Next with `title="◀️ Previous"` / `title="▶️ Next"`.
- `data-itm-db-import-endpoint="index.php"` on index table for Excel import.
- Actions column uses `itm-actions-cell` + `itm-actions-wrap` (no `flex-wrap` — share + CRUD buttons stay on one line).
- Gate-excluded UI config checks for missing `delete.php`, `list_all.php`, and bulk-delete toolbar are reviewed in `scripts/data/ui_configuration_reviewed.json` — inline per-row delete uses `index_logic.php`.
- Create/edit profile photo uses the employees-style upload UI (`includes/profile_photo_fields.php`: circular drag-and-drop target, `itm-upload-helper.js`, **PNG only**). Hint: `Drag and drop or click to upload PNG.` Do not nest `<label for>` inside a click-bound upload target without the shared label guard in `itm-upload-helper.js` (prevents double file-picker).

## 6. API Actions (If Applicable)
- **toggle_favourite** (POST on `index_logic.php`) — CSRF + `employee_id` scope; AJAX star toggle on list.
- **import_excel_rows** (JSON POST on `index.php`) — bulk import with `data-itm-db-import-endpoint="index.php"`; requires unlocked vault; import encrypts PII via `pc_encrypt_contact_import_row_values()`.
- **create_share_session** (POST `index.php?ajax_action=create_share_session`) — temporary QR / 6-digit share via `private_contact_share_sessions` + `join.php` (vault must be unlocked).

## 7. File Structure
- `index.php` — HTML list view (search/sort/pagination wiring, Settings list header, `import_excel_rows` JSON handler).
- `index_logic.php` — auth, POST handlers (toggle favourite, inline delete).
- `private_contacts_list_helpers.php` — server-side list query (hydrate, search, sort, pagination).
- `pc_vault_bootstrap.php`, `pc_vault_helpers.php` — vault unlock/lock UI (`itm_vault_unlock.php`) and encrypt/decrypt/hydrate helpers.
- `pc_share_helpers.php`, `join.php` — temporary QR/WhatsApp/Outlook share sessions.
- `pc_contact_form_helpers.php` — POST → plaintext map for create/edit encryption.
- `create.php`, `edit.php`, `view.php` — CRUD screens.
- `edit_form.php` — shared form sections for create/edit.
- `includes/profile_photo_fields.php` — employees-matching photo UI for create/edit.
- `includes/private_contact_photo.php` — photo URL + upload store helpers.

## 8. Multi-Tenant Rules
- `company_id` plus **mandatory** `employee_id` filter on every SELECT/UPDATE/DELETE.

## 9. Audit Logging Requirements
- **Private data (no audit):** `private_contacts` is exempt from `audit_logs` and database audit triggers per `AGENTS.md` → **Private data — no audit trail**. Do not add PHP audit hooks for contact mutations.

## 10. Common Pitfalls
- Do not reuse company contacts visibility rules — this module is user-private only. [Cursor-Valid]
- Photo paths must stay inside the user's Private explorer segment. [Cursor-Valid]
- Do not drop `employee_id` from DELETE/WHERE clauses. [Cursor-Valid]
- Profile photo upload: use `includes/profile_photo_fields.php` + `pc_contact_photo_store_upload()`; **PNG only** (unlike employees, which accepts PNG and JPG). Type resolution uses `pc_contact_photo_resolve_png_extension()`. Upload failures surface `photo_error` on create/edit redirects. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM private_contacts WHERE employee_id = ? AND company_id = ?");
$stmt->bind_param("ii", $employeeId, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
standard CRUD fixes allowed, but preserve per-user privacy on every code path.
