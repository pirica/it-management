# Secure Multi-Tenant File Explorer Subsystem

Comprehensive documentation for the secure, multi-tenant file explorer subsystem (`modules/explorer/`) including directory layout, access control lists, upload hardening policies, zip download contracts, and vault cryptographic gates.

---

## 1. Intent & Purpose

The **Explorer** module provides a secure, folder-based multi-tenant file manager. To satisfy strict privacy and security mandates (such as blocking arbitrary remote code execution and directory traversal), the subsystem implements multi-stage path normalization, server-side ACL validation, zero-knowledge vault-key gates, and physical HTTP-access blocks across all directory levels.

---

## 2. Directory Layout & Access Boundaries

The physical storage for tenant files is anchored at `files/{company_id}/`. Below this base directory, the file system segments files logically and applies distinct permission boundaries:

```
files/{company_id}/
├── Common/                          <-- Accessible to all tenant employees
├── Departments/
│   ├── {dept_id}/                   <-- Accessible only to department members
├── Private/
│   ├── {username}_{employee_id}/    <-- Accessible only to the owning employee
└── Trash/                           <-- User-scoped soft-delete recycle bin
```

### Access Control Lists (ACLs)

The explorer API (`modules/explorer/api.php`) and directory scanner enforce strict path boundary checks:

| Folder Segment | Path Prefix | Access Rules |
|---|---|---|
| **Common** | `Common/` | Read/write for all users under the active company. |
| **Departments** | `Departments/{dept_id}/` | Verified via active user department membership. Regular users are blocked if their `department_id` does not match `{dept_id}`. |
| **Private** | `Private/{username}_{employee_id}/` | Owner-only directory matching the signed-in session's `employee_id`. All other users are blocked. |
| **Trash** | `Trash/` | Logical user-scoped recycle bin mirroring the segment rules of live storage. |

### API Protection Guidelines
- **Path Normalization:** The utility function `get_full_path()` normalizes backslashes to forward slashes, trims redundant slashes, and explicitly rejects path traversal sequences (`..`).
- **Root Folder Blocking:** The explorer API explicitly blocks users from scanning or modifying the root `Private/` or `Departments/` folders directly. Requests to list these directories are rejected, preventing cross-user and cross-department metadata leakage.
- **Root Upload Restrictions:** Creating files, folders, or uploading assets directly in the root `Home` (base `files/{company_id}/`), `Private` root, or `Departments` root is strictly prohibited.

---

## 3. Recycle Bin & Trash ACLs

### Trash Visibility at Home
To keep the file explorer clean, the physical `Trash/` folder is omitted from physical directory lists at the `Home` level. Instead:
1. The sidebar and the main list display a virtual `Trash` entry (using the 🗑️ icon) **only** if the function `explorer_user_has_visible_trash_items()` detects deleted items matching the current user's ACL.
2. If no visible deleted items exist for the active user, the virtual Trash folder is hidden from `Home`.

### Ancestor Folder Filtering
When listing items in the Recycle Bin, the helper `explorer_filter_trash_list_to_leaf_items()` filters out parent folders that were implicitly created to hold a nested file during soft-delete. Only the exact leaf file/folder deleted by the user is displayed, preventing empty folder clutter while preserving the ability to restore items back to their original path structure.

---

## 4. `downloadZip` Security Contract

To prevent bulk data exfiltration or arbitrary system file leakage, the `downloadZip` operation (`api.php?downloadZip=1`) operates under a strict, non-negotiable contract:
- Users may **only** request a recursive ZIP archive of their own primary Private folder (`Private/{username}_{employee_id}`).
- All other path targets—including `Home`, `Common/`, individual department folders (`Departments/{dept_id}`), other users' private folders, and even subfolders inside their own private folder—are rejected with an access-denied error.

---

## 5. Directory Hardening & `deny_http` Policies

The filesystem employs a "Defense in Depth" security posture to neutralize uploaded script execution vectors.

### Managed `.htaccess` Policies
Every directory created under `files/` is configured using `itm_ensure_files_storage_directory()` or `itm_ensure_upload_directory_chain()`. These helpers automatically force-write and overwrite an `.htaccess` configuration with the `deny_http` policy:

```apache
# ITM files hardening
RewriteEngine On
RewriteRule ^ - [F]
Options -Indexes -ExecCGI
```

- **Effect:** Blocks all direct web-server (HTTP) access to files in the directory tree.
- **File Serving:** Assets must be read and streamed to the UI through the secure streaming script `modules/explorer/file.php` after verifying the user's active session and ACL permissions. Direct static links under `files/` are completely disabled.
- **Index Protection:** An empty `index.html` is force-written to every path segment (from parent to leaf nodes) to prevent directory index listing.
- **Dotfile Blocks:** Uploading `.htaccess`, `.htpasswd`, or other dotfiles through the file explorer is blocked globally.

---

## 6. Cryptographic Vault Gates

To ensure zero-knowledge data privacy, access to personal files inside the private user folders is tied to the vault master key:

- **Verification:** Scanning, uploading, previewing, or downloading assets under `Private/{username}_{employee_id}/` (except public profile assets served via `file.php`) requires a valid, active `$_SESSION['vault_key']`.
- **Lock Screen:** If the vault is locked, the interface serves a vault lock overlay.
- **Two-Factor Integration:** When the user has TOTP enabled (`employees.totp_enabled = 1`), unlocking the folder requires both the master vault key and a valid 6-digit authenticator code via `includes/itm_vault_unlock.php`.

---

## 7. QR & Folder Sharing

The explorer supports secure, temporary external sharing:
1. **Creation:** Inside an unlocked vault context, users can select a folder or file under `Common/`, `Departments/`, or their own `Private/` folder and request a share session.
2. **Payload:** The system generates a temporary session in `share_sessions` (`module_slug = explorer`) storing the scoped path and file list.
3. **Distribution:** Generates a secure QR code and copyable Outlook/WhatsApp links.
4. **Access Gate:** Company-level module share is evaluated via the admin matrix (`company_module_share.enabled`).
5. **Consumption:** External users scan or click to open `join.php`, listing shared file names and downloading assets securely through token-scoped `share_file.php` requests. Share sessions expire automatically.

---

## 8. Verifications & Regression Scripts

Engineers must run the following diagnostic scripts from the repository root to verify filesystem security after modifications:

```bash
# 1. Verify path logic and boundary checks
php scripts/test_explorer_paths.php

# 2. Audit ZIP leak prevention and bulk-download constraints
php scripts/verify_explorer_zip_leak.php

# 3. Test htaccess injection hardening and script execution blocks
php scripts/verify_explorer_rce_htaccess.php
php scripts/verify_explorer_rce_marker.php

# 4. Execute unit test suite covering Trash filtering and ACL bounds
php scripts/run_tests.php --filter Explorer
```
