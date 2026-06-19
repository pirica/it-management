# Passwords Module Agent Instructions

## Overview
The Passwords module provides a secure, private manager for user credentials. It features a folder hierarchy, encryption at rest using AES-256-CBC, and a LastPass-style password generator.

## Security Standards
- **Private Data:** All queries for password folders and entries MUST be scoped to the logged-in user (`user_id = $_SESSION['employee_id']`).
- **Encryption:** Passwords MUST be stored encrypted in the database using the `itm_encrypt()` helper. Decryption is only performed in memory when the vault is unlocked.
- **Vault State:** The master key is stored in the `$_SESSION['vault_key']`. When this key is absent, the module MUST prompt for the master key and hide all decrypted data.
- **Masking:** Password fields in the UI MUST be masked by default with a toggle visibility button.
- **Copy-to-Clipboard:** Always provide a 🗐 icon for copying fields (Account, Login, Password, Website, Comments) to the clipboard.

## Data Structure
- `password_folders`: Stores the user's folder hierarchy. Supports nested folders via `parent_id`.
- `password_entries`: Stores encrypted credentials linked to a user and optional folder.

## Import/Export
- **Formats:** Supports XLSX, CSV, PDF, and TXT for export.
- **Import:** Supports Microsoft Edge and KeePass CSV formats. Field mapping is automated based on headers.

## Development Tips
- AJAX operations are centralized in `ajax_handler.php`.
- Export operations are handled by `export_handler.php`.
- The frontend uses a three-column responsive layout (Generator | Tree | List).
