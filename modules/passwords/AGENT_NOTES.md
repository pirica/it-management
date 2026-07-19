# AGENT_NOTES.md - Passwords

---

## 1. Module Purpose

Secure private password manager with vault encryption. It allows users to store credentials in a folder hierarchy, featuring a LastPass-style password generator and AES-256-CBC encryption at rest.

---

## 2. Key Tables

- **password_folders** — hierarchical folders for passwords
- **password_entries** — stores the encrypted credentials

---

## 3. Required Relationships

- **password_folders** → depends on **employees** (`employee_id`, `ON DELETE CASCADE`)
- **password_folders** → self-referencing for hierarchy (`parent_id`, `ON DELETE SET NULL`)
- **password_entries** → depends on **employees** (`employee_id`, `ON DELETE CASCADE`)
- **password_entries** → depends on **password_folders** (`folder_id` column stores the folder ID, `ON DELETE CASCADE`)

---

## 4. Business Rules (Critical for Agents)

- **Vault Security**: Data is encrypted at rest using `itm_encrypt` and a session-based `vault_key`.
- **Master Key Change**: `user-config.php` re-encrypts all `password_entries` inside a DB transaction via `includes/itm_vault_master_key.php` (`itm_vault_reencrypt_password_entries()`); on failure the transaction rolls back and `vault_key_hash` is not updated.
- **Private Data**: Passwords and folders are private to the `employee_id` and MUST be scoped to the logged-in employee (`employee_id = $_SESSION['employee_id']`).
- **Session Key**: Decryption requires `$_SESSION['vault_key']` (SHA-256 hash of master key) to be populated. If absent, the module MUST prompt for the master key.
- **Encryption**: Passwords MUST be stored encrypted in the database using the `itm_encrypt()` helper.

---

## 5. UI Behavior Requirements

- **Three-Column Layout**: Responsive UI with Password Generator, Folder Tree, and Entry List.
- **List header (Settings UI):** when vault is unlocked, `index.php` uses `data-itm-new-button-managed="server"` with centered `sanitize($moduleListHeading)` from `itm_sidebar_label_for_module()`; `new_button_position` gates left/right ➕ create controls (`btn btn-primary itm-list-new-button`, opens entry modal — no `create.php`).
- **Bulk delete:** when employee row count `>= records_per_page`, show standard `bulk-delete-form` (Select to Delete, Cancel, Clear Table) posting to `delete.php` → `index.php` (`crud_action=delete`); AJAX-rendered rows include `ids[]` checkboxes and rebind `bulk-delete-selection.js` via `window.itmInitBulkDeleteSelection()` after `loadEntries()`.
- **Masking**: Password fields in the UI MUST be masked by default with a toggle visibility button.
- **Special import/export (not table-tools):** Tools menu drives CSV/Excel import modals and `exportVault()` / `export_handler.php`. Entry list `<table>` uses `data-itm-no-import-excel="1"`, `data-itm-no-export-excel="1"`, and `data-itm-no-export-pdf="1"` so index compliance does not require `data-itm-db-import-endpoint`. Actions `th`/`td` (including JS-rendered rows) use `itm-actions-cell` + `data-itm-actions-origin="1"`.
- **Copy-to-Clipboard**: Provide a 🗐 icon for copying fields (Account, Login, Password, Website, Comments) to the clipboard.
- **Password Generator**: Features length slider, character type toggles, and strength meter. Manual edits to the generated password field update the displayed length and strength meter live.
- **AJAX Driven**: Folder and entry CRUD operations are handled via AJAX to `ajax_handler.php`.
- **Folder drag-and-drop:** folders can be reparented via drag-and-drop in the sidebar tree (mirrors `modules/bookmarks/`). Dropping onto a destination with a same-named sibling prompts to merge (move entries/subfolders into the existing folder and delete the source) or keep both folders with the same name. Edit-folder parent changes use the same merge prompt.
- **QR / code share (`join.php`):** vault-unlocked temporary read links (30 min). `password_share_sessions` stores plaintext `payload_json` snapshot (decrypted fields). UI: 📱, `images/whatsapp.svg`, and 📨 on entry list actions and `view.php`; modal via `includes/itm_qr_share_modal.php`. Public page: `join.php` (`ITM_QR_SHARE_PUBLIC`). Regression: `php scripts/verify_qr_share_modules.php`, `php scripts/verify_whatsapp_share.php`, `php scripts/verify_outlook_share.php`.
- **View:** `view.php` read-only entry detail (vault-unlocked) with 🔎 list action, masked password + copy, share controls, and ✏️ link back to `index.php?edit_entry=` modal.

---

## 6. API Actions (If Applicable)

All POST to `ajax_handler.php` with `action` and `csrf_token`. Responses are JSON.

- **list_folders** — retrieves folder tree for sidebar
- **save_folder** — create/update folder (`id`, `name`, `parent_id`, optional `merge_into_folder_id`)
- **move_folder** — reparent folder (`folder_id`, `new_parent_id`, optional `merge_into_folder_id`); uses `passwords_folder_helpers.php`
- **delete_folder** — remove folder
- **list_entries** — retrieves entries for selected folder; supports search (`folder_id`, `search`)
- **get_entry** — retrieves single entry for editing (decrypts password)
- **save_entry** — create/update encrypted entry (`id`, `folder_id` maps to `folder_id` column, `account`, `login_name`, `password`, `website`, `comments`)
- **delete_entry** — remove entry
- **import_rows** — JSON-based import for Excel/XLSX
- **import_csv** — CSV import supporting Edge and KeePass formats
- **create_share_session** — owner-scoped temporary QR/code share (`password_share_sessions`); requires unlocked vault

---

## 7. File Structure

- **index.php** — main interface (Generator | Tree | List)
- **delete.php** — bulk delete / clear table wrapper (`crud_action=delete`)
- **view.php** — read-only entry detail with share actions
- **ajax_handler.php** — central AJAX handler for encryption and CRUD
- **passwords_folder_helpers.php** — folder move/merge helpers (`pwd_move_folder()`, `pwd_merge_folder_into()`)
- **passwords_share_helpers.php** — QR share session builder (`passwords_share_create_session()`)
- **join.php** — public 6-digit / token join page for shared password snapshots
- **export_handler.php** — secure export handler (XLSX, CSV, PDF, TXT)

---

## 8. Multi-Tenant Rules

- Strictly scoped by `employee_id` for user privacy. Data is NOT shared across the company.

---

## 9. Audit Logging Requirements

- **Private data (no audit):** `password_folders` and `password_entries` must not write to `audit_logs` and have no `trg_*_audit_*` triggers in `database.sql` (see `AGENTS.md` → **Private data — no audit trail**). Do not add PHP audit hooks that would log vault metadata or ciphertext.

---

## 10. Common Pitfalls

- **Losing the Master Key**: Data is unrecoverable if the master key is lost. [Cursor-Valid]
- **Plain Text Exposure**: Ensure logs do not capture unencrypted passwords. [Cursor-Valid]
- **Session Timeout**: If `$_SESSION['vault_key']` is lost, the vault locks immediately. [Cursor-Valid]

---

## 11. Examples of Safe Code Patterns

### Safe SELECT (Private)

```php
$stmt = mysqli_prepare($conn, "SELECT * FROM password_entries WHERE id = ? AND employee_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$entry = mysqli_fetch_assoc($res);
```

### Safe INSERT (Encrypted)

```php
$encrypted = itm_encrypt($plainText, $_SESSION['vault_key']);
// folder_id column stores the folder ID
$stmt = mysqli_prepare($conn, "INSERT INTO password_entries (employee_id, folder_id, account, password) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iiss', $user_id, $folder_id, $account, $encrypted);
mysqli_stmt_execute($stmt);
```

---

## 12. Module Owner Notes (Optional)

Highly sensitive module. Follow all security guidelines in `AGENTS.md`. Decryption only happens in memory when the vault is unlocked.
