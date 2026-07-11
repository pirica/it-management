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
- **Masking**: Password fields in the UI MUST be masked by default with a toggle visibility button.
- **Copy-to-Clipboard**: Provide a 🗐 icon for copying fields (Account, Login, Password, Website, Comments) to the clipboard.
- **Password Generator**: Features length slider, character type toggles, and strength meter.
- **AJAX Driven**: Folder and entry CRUD operations are handled via AJAX to `ajax_handler.php`.

---

## 6. API Actions (If Applicable)

All POST to `ajax_handler.php` with `action` and `csrf_token`. Responses are JSON.

- **list_folders** — retrieves folder tree for sidebar
- **save_folder** — create/update folder (`id`, `name`, `parent_id`)
- **delete_folder** — remove folder
- **list_entries** — retrieves entries for selected folder; supports search (`folder_id`, `search`)
- **get_entry** — retrieves single entry for editing (decrypts password)
- **save_entry** — create/update encrypted entry (`id`, `folder_id` maps to `folder_id` column, `account`, `login_name`, `password`, `website`, `comments`)
- **delete_entry** — remove entry
- **import_rows** — JSON-based import for Excel/XLSX
- **import_csv** — CSV import supporting Edge and KeePass formats

---

## 7. File Structure

- **index.php** — main interface (Generator | Tree | List)
- **ajax_handler.php** — central AJAX handler for encryption and CRUD
- **export_handler.php** — secure export handler (XLSX, CSV, PDF, TXT)

---

## 8. Multi-Tenant Rules

- Strictly scoped by `employee_id` for user privacy. Data is NOT shared across the company.

---

## 9. Audit Logging Requirements

- Database triggers for standard audit logging are NOT present in `database.sql` for these tables. Manual application-level logging is not implemented to avoid plain text exposure in logs.

---

## 10. Common Pitfalls

- **Losing the Master Key**: Data is unrecoverable if the master key is lost.
- **Plain Text Exposure**: Ensure logs do not capture unencrypted passwords.
- **Session Timeout**: If `$_SESSION['vault_key']` is lost, the vault locks immediately.

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
