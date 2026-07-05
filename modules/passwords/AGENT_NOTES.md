# AGENT_NOTES.md - Passwords

## 1. Module Purpose
Secure private password manager with vault encryption. It allows users to store credentials in a folder hierarchy.

## 2. Key Tables
- **password_entries** — stores the encrypted credentials.
- **password_folders** — hierarchical folders for passwords.

## 3. Required Relationships
- **password_entries** → depends on **employees** (ownership via `employee_id`).
- **password_folders** → depends on **employees** (`employee_id`).

## 4. Business Rules (Critical for Agents)
- **Vault Security**: Data is encrypted at rest using `itm_encrypt` and a session-based `vault_key`.
- **Master key change**: `user-config.php` re-encrypts all `password_entries` inside a DB transaction via `includes/itm_vault_master_key.php` (`itm_vault_reencrypt_password_entries()`); on failure the transaction rolls back and `vault_key_hash` is not updated. Session `vault_key` updates only after a successful commit.
- **Strict Ownership**: Passwords are private to the `employee_id` and are NOT shared across the company unless explicitly moved to a shared module (though not supported in the core table).
- **Session Key**: Decryption requires `$_SESSION['vault_key']` to be populated during login.

## 5. UI Behavior Requirements
- **Encrypted Fields**: Passwords must never be displayed in plain text without explicit user action (e.g., "Show Password").
- **Folder Tree**: Navigation via a sidebar folder structure.

## 6. API Actions (If Applicable)
All POST to `ajax_handler.php` with `action` (JSON responses, `employee_id` scoped):

| Action | Purpose |
|--------|---------|
| `list_folders` | Folder tree for sidebar |
| `save_folder` | Create/update folder (`parent_id` nesting) |
| `delete_folder` | Remove folder (entries may move to root) |
| `list_entries` | Entries for selected folder; global search (`folder_id=0`) also matches `password_folders.name` via EXISTS |
| `get_entry` | Single entry for edit modal (decrypt password in memory) |
| `save_entry` | Create/update encrypted entry |
| `delete_entry` | Delete entry |
| `import_rows` / `import_csv` | Edge/KeePass CSV import with header mapping |

- **export_handler.php** — XLSX, CSV, PDF, TXT export (decrypt with session `vault_key` only).

## 7. File Structure
- **index.php** — three-column UI (generator | tree | list).
- **ajax_handler.php** — encryption, folder/entry CRUD, import.
- **export_handler.php** — secure export formats.

## 8. Multi-Tenant Rules
- Scoped by `employee_id` for privacy.

## 9. Audit Logging Requirements
- Database triggers on **password_entries** / **password_folders** when present in schema; never log decrypted values.

## 10. Common Pitfalls
- **Losing the Master Key**: If the master key is lost, the data is unrecoverable.
- **Plain Text Exposure**: Ensure logs do not capture the unencrypted password.

## 11. Examples of Safe Code Patterns

### Safe INSERT (Encrypted)
```php
$encrypted = itm_encrypt($plainText, $_SESSION['vault_key']);
$stmt = $conn->prepare("INSERT INTO password_entries (employee_id, account, login_name, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $employeeId, $account, $login, $encrypted);
$stmt->execute();


# Passwords Module Agent Instructions

## Overview
The Passwords module provides a secure, private manager for user credentials. It features a folder hierarchy, encryption at rest using AES-256-CBC, and a LastPass-style password generator.

## Security Standards
- **Private Data:** All queries for password folders and entries MUST be scoped to the logged-in employee (`employee_id = $_SESSION['employee_id']`).
- **Encryption:** Passwords MUST be stored encrypted in the database using the `itm_encrypt()` helper. Decryption is only performed in memory when the vault is unlocked.
- **Vault State:** The master key is stored in the `$_SESSION['vault_key']`. When this key is absent, the module MUST prompt for the master key and hide all decrypted data.
- **Masking:** Password fields in the UI MUST be masked by default with a toggle visibility button.
- **Copy-to-Clipboard:** Always provide a 🗐 icon for copying fields (Account, Login, Password, Website, Comments) to the clipboard.

## Data Structure
- `password_folders`: Stores the user's folder hierarchy. Supports nested folders via `parent_id`.
- `password_entries`: Stores encrypted credentials linked to an employee and optional folder.

## Import/Export
- **Formats:** Supports XLSX, CSV, PDF, and TXT for export.
- **Import:** Supports Microsoft Edge and KeePass CSV formats. Field mapping is automated based on headers.

## Development Tips
- AJAX operations are centralized in `ajax_handler.php`.
- Export operations are handled by `export_handler.php`.
- The frontend uses a three-column responsive layout (Generator | Tree | List).
```
## 12. Module Owner Notes (Optional)
The most sensitive module in the system. Follow all security guidelines in `AGENTS.md`.


# Passwords Module Agent Instructions

## Overview
The Passwords module provides a secure, private manager for user credentials. It features a folder hierarchy, encryption at rest using AES-256-CBC, and a LastPass-style password generator.

## Security Standards
- **Private Data:** All queries for password folders and entries MUST be scoped to the logged-in employee (`employee_id = $_SESSION['employee_id']`).
- **Encryption:** Passwords MUST be stored encrypted in the database using the `itm_encrypt()` helper. Decryption is only performed in memory when the vault is unlocked.
- **Vault State:** The master key is stored in the `$_SESSION['vault_key']`. When this key is absent, the module MUST prompt for the master key and hide all decrypted data.
- **Masking:** Password fields in the UI MUST be masked by default with a toggle visibility button.
- **Copy-to-Clipboard:** Always provide a 🗐 icon for copying fields (Account, Login, Password, Website, Comments) to the clipboard.

## Data Structure
- `password_folders`: Stores the user's folder hierarchy. Supports nested folders via `parent_id`.
- `password_entries`: Stores encrypted credentials linked to an employee and optional folder.

## Import/Export
- **Formats:** Supports XLSX, CSV, PDF, and TXT for export.
- **Import:** Supports Microsoft Edge and KeePass CSV formats. Field mapping is automated based on headers.

## Development Tips
- AJAX operations are centralized in `ajax_handler.php`.
- Export operations are handled by `export_handler.php`.
- The frontend uses a three-column responsive layout (Generator | Tree | List).

