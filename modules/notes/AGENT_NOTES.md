# AGENT_NOTES.md - Notes

## 1. Module Purpose
Google Keep–style personal and shared notes for the active company. Supports pinning, importance, image attachments (`images_json`), colour, labels, and sharing via `shared_with_json`. **Private notes** (no share targets) encrypt title, content, checklist, and labels at rest via the vault — same session key as Passwords and Bookmarks.

## 2. Key Tables
- **notes** — main note records (`title`, `title_hash`, `content`, `checklist_json`, `is_pinned`, `is_important`, `images_json`, `shared_with_json`, `color`, `employee_id`). Standard audit and deletion tracking columns (`deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) are supported and managed by the application code and DB triggers.
- **note_labels** — per-user label/tag names (`label`, `label_hash`) used when filtering and importing.
- **note_share_sessions** — temporary QR / 6-digit join snapshots (`payload_json`, `share_code`, `access_token`, `expires_at`). Private-data exempt (no `audit_logs`).

## 3. Required Relationships
- **notes** → depends on **companies** (`company_id`), **employees** (`employee_id`, share targets).
- **notes** → uses **note_labels** for tag metadata.
- Visibility helpers live in `includes/notes_visibility.php`.
- Vault helpers: `modules/notes/notes_vault_bootstrap.php`, `modules/notes/notes_vault_helpers.php`.

## 4. Business Rules (Critical for Agents)
- A user sees only their own notes or notes shared with them (`itm_notes_visibility_sql()`).
- `shared_with_json` is a JSON array of user IDs. When non-empty, note body fields stay **plaintext** for recipients.
- When `shared_with_json` is empty, encrypt `title`, `content`, and `checklist_json` with `itm_encrypt()` + `$_SESSION['vault_key']`; store `title_hash` for lookups.
- `note_labels.label` is always encrypted for the owner; use `label_hash` for tag filter, rename, and duplicate checks.
- Import maps tag names and usernames to tenant-scoped IDs before insert; requires unlocked vault.
- Standard CSRF on all POST handlers via `itm_require_post_csrf()` (form/AJAX); JSON `import_excel_rows` validates `csrf_token` from the request body with `itm_validate_csrf_token()`.
- Master key change re-encrypts private notes via `itm_vault_reencrypt_notes()` (`user-config.php`).

## 5. UI Behavior Requirements
- **Vault lock screen** when vault is locked on index, list_all, create, and private owned edit/view (mirrors bookmarks).
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract. Row meta is for soft-delete display only; this module stays **private-data exempt** from `audit_logs` triggers.
- Custom card/grid UI (not standard flattened table CRUD on index).
- **Table view (`list_all.php`):** when `$totalRows >= $perPage`, show bulk toolbar (`bulk-delete-form`, Select to Delete, Cancel, Clear Table) with row `ids[]` checkboxes; posts to `delete.php` → `index.php` (`crud_action=delete`). Include `bulk-delete-selection.js` in index HTML (gate scans index, not header only).
- **List search/sort:** `list_all` uses `notes_query_notes_for_list()` — hydrate decrypt, then in-memory `notes_row_matches_search()` and `notes_compare_note_rows()` (no SQL `LIKE` on ciphertext). GET `search`, `sort`, `dir`, `page`; sortable headers with ▲/▼ on title, reminder, pinned, important, archived.
- Sidebar filters: pinned, images, important, shared, labels.
- Supports `import_excel_rows` JSON on index/list_all.
- **Export/import:** both card and table views use the same hidden export `<table>` in the tools card (`data-itm-db-import-endpoint` = `index.php` or `list_all.php`). The visible `list_all` grid opts out of `table-tools.js` (`data-itm-no-import-excel` / `data-itm-no-export-*`) so export/import buttons are not duplicated.
- Hide `company_id` from views.
- **Search:** after hydrate, matches title, content, decrypted label names, and shared-with employee names in PHP (not SQL `LIKE` on ciphertext).
- **Responsive:** sidebar stacks above note list below 768px (`index.php` inline CSS).

## 6. API Actions (If Applicable)
- **AJAX on index** — pin, archive, share, label, image upload mutations; use `itm_notes_json_mutation_response()` (404 when `affected_rows === 0`). `quick_add` and label mutations require unlocked vault.
- **import_excel_rows** (JSON POST on `index.php` / `list_all.php`) — resolves tags via **note_labels** and share targets via usernames; requires unlocked vault.
- **download_all_images** — ZIP of note attachments via `itm_notes_resolve_image_path()` (never raw JSON paths).
- **QR / code share (`join.php`):** owner-only temporary read links (30 min). `note_share_sessions` stores `share_code`, `access_token`, and a plaintext `payload_json` snapshot (private notes require unlocked vault at create time). UI: 📱 on card rows, table actions, and view; `images/whatsapp.svg` opens WhatsApp with join link + code (`js/itm-whatsapp-share.js`); 📨 opens Outlook/mail compose (`js/itm-outlook-share.js`); QR modal uses `js/qrcode.min.js`. **`create_share_session` JSON** includes `has_images` when the snapshot `payload_json` contains image filenames. **WhatsApp / mailto only:** when `has_images` is true, `js/itm-share-no-attachments-prompt.js` shows a one-time “Share link only” confirm (checkbox **Don’t show again** → `localStorage` key `itm_share_no_attachments_dismissed`); notes without images and other modules skip the prompt. Public pages: `join.php`, `share_asset.php` (`ITM_NOTES_SHARE_PUBLIC`). Regression: `php scripts/verify_notes_share.php`, `php scripts/verify_whatsapp_share.php`, `php scripts/verify_outlook_share.php`.

## 7. File Structure
- `index.php` — main UI, filters, import API, CRUD routing.
- `notes_vault_bootstrap.php`, `notes_vault_helpers.php` — vault unlock UI and encrypt/decrypt helpers.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — standard entry wrappers.

## 8. Multi-Tenant Rules
- All queries filter by `company_id` and visibility (`employee_id` / `shared_with_json`).
- Notes cannot be moved between companies.

## 9. Audit Logging Requirements
- **Private data (no audit):** `notes` and `note_labels` must not write to `audit_logs` and have no `trg_*_audit_*` triggers in `database.sql` (see `AGENTS.md` → **Private data — no audit trail**).

## 10. Common Pitfalls
- Do not list another user's private notes — always apply `itm_notes_visibility_sql()`. [Cursor-Valid]
- View/edit GET load must use `itm_notes_fetch_visible_by_id()` — do not SELECT by `id + company_id` alone. [Cursor-Valid]
- Do not store share targets as plain text; use `shared_with_json`. [Cursor-Valid]
- Label import must resolve names against decrypted/hydrated labels for the current user. [Cursor-Valid]
- **`images_json` attachments:** store leaf filenames only. ZIP download (`download_all_images`) resolves paths via `itm_notes_resolve_image_path()` in `includes/notes_visibility.php` — never concatenate raw JSON values into filesystem paths. [Cursor-Valid]
- **AJAX mutations:** visibility-scoped handlers call `itm_notes_json_mutation_response()` — return HTTP 404 with `ok:false` when `affected_rows === 0` (no misleading success on blocked delete). **`single_delete`** soft-deletes live rows (`active=0`, `deleted_by`, `deleted_at`); hard `DELETE` only when already inactive. Regression: `php scripts/verify_notes_ajax_contract.php` (attacker blocked; owner note stays `active=1` with `deleted_at IS NULL`). [Cursor-Valid]
- **Vault:** do not SQL-search encrypted `title`/`content`/`label` — hydrate then filter in PHP. Tag sidebar filter uses `label_hash`. [Cursor-Valid]
- **Regression:** `php scripts/verify_notes_vault.php` after changing vault helpers or note persistence. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT with visibility
```php
$sql = "SELECT * FROM notes WHERE company_id = ? AND (" . itm_notes_visibility_sql() . ")";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $companyId, $loggedUserId, $loggedUserId);
$stmt->execute();
```

### Encrypt private note on save
```php
$prepared = notes_prepare_note_fields_for_storage($title, $content, $checklistJson, $sharedWithJson);
if ($prepared === null) {
    // Vault locked — block private write
}
```

### Safe single-record view/edit load
```php
$data = itm_notes_fetch_visible_by_id($conn, $editId, $companyId, $loggedUserId, true);
if (!$data) {
    header('Location: index.php');
    die();
}
notes_hydrate_note_row($data, $loggedUserId);
```

## 12. Module Owner Notes (Optional)
Bespoke UI module — module browser QA may treat some standard CRUD steps as N/A; verify behaviour manually after changes. Run `php scripts/verify_notes_vault.php` when touching encryption.
