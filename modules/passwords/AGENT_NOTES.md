# AGENT_NOTES.md - Passwords

## 1. Module Purpose
Secure private password manager with vault encryption. It allows users to store credentials in a folder hierarchy.

## 2. Key Tables
- **password_entries** — stores the encrypted credentials.
- **password_folders** — hierarchical folders for passwords.

## 3. Required Relationships
- **password_entries** → depends on **users** (Ownership).
- **password_folders** → depends on **users**.

## 4. Business Rules (Critical for Agents)
- **Vault Security**: Data is encrypted at rest using `itm_encrypt` and a session-based `vault_key`.
- **Strict Ownership**: Passwords are private to the `user_id` and are NOT shared across the company unless explicitly moved to a shared module (though not supported in the core table).
- **Session Key**: Decryption requires `$_SESSION['vault_key']` to be populated during login.

## 5. UI Behavior Requirements
- **Encrypted Fields**: Passwords must never be displayed in plain text without explicit user action (e.g., "Show Password").
- **Folder Tree**: Navigation via a sidebar folder structure.

## 6. API Actions (If Applicable)
- **ajax_handler.php** — handles async CRUD operations.
- **export_handler.php** — secure export of credentials.

## 7. File Structure
- **index.php** — main interface.
- **ajax_handler.php** — backend logic for encryption and storage.

## 8. Multi-Tenant Rules
- Scoped by `user_id` for privacy.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Losing the Master Key**: If the master key is lost, the data is unrecoverable.
- **Plain Text Exposure**: Ensure logs do not capture the unencrypted password.

## 11. Examples of Safe Code Patterns

### Safe INSERT (Encrypted)
```php
$encrypted = itm_encrypt($plainText, $_SESSION['vault_key']);
$stmt = $conn->prepare("INSERT INTO password_entries (user_id, account, login_name, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $userId, $account, $login, $encrypted);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The most sensitive module in the system. Follow all security guidelines in `AGENTS.md`.
