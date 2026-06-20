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
```

## 12. Module Owner Notes (Optional)
The most sensitive module in the system. Follow all security guidelines in `AGENTS.md`.
