# Security Audit Report - June 2026

This report summarizes critical and high-severity security vulnerabilities identified during the scheduled application-security review.

## Summary of Findings

| ID | Title | Severity | Location | Status |
|---|---|---|---|---|
| 1 | Authenticated Remote Code Execution (RCE) via File Upload | Critical | `modules/explorer/api.php` | Remediated |
| 2 | Privilege Escalation via User Profile Modification | High | `modules/users/index.php` | Remediated |
| 3 | Unauthorized Access to Role Module Permissions | High | `modules/role_module_permissions/index.php` | Remediated |

---

## Finding 1: Authenticated Remote Code Execution (RCE) via File Upload

- **Status:** Remediated — blocked executable extensions on upload; `deny_http` hardening on `files/` tree via `itm_ensure_files_storage_directory()`. Regression: `php scripts/verify_explorer_rce_htaccess.php`.
- **Severity:** Critical
- **Location:** `modules/explorer/api.php`
- **Attacker:** Any authenticated user.
- **Input:** `$_FILES` via the `upload` action.
- **Attack Path:**
    1. Attacker logs into the system.
    2. Attacker uses the Explorer module to upload a PHP script (e.g., `cmd.php`) to a common or private folder.
    3. The file is saved in the `files/{company_id}/` directory tree.
    4. Attacker accesses the script directly via `http://localhost/files/{company_id}/Common/cmd.php`.
- **Impact:** Complete system compromise. The attacker can execute arbitrary commands on the server under the web server user's privileges.
- **Remediation:**
    - Implement a strict `.htaccess` file in the `files/` root directory to disable PHP execution (e.g., `php_flag engine off`).
    - Use `itm_ensure_upload_directory()` to apply the project's standard upload hardening policy to all directories created within the `files/` tree.

---

## Finding 2: Privilege Escalation via User Profile Modification

- **Status:** Remediated — `itm_require_admin()` on all Users entry points; non-admins cannot set `role_id` or `access_level_id`.
- **Severity:** High
- **Location:** `modules/users/index.php`
- **Attacker:** Any authenticated user.
- **Input:** `role_id` and `access_level_id` in a POST request to `edit` action.
- **Attack Path:**
    1. Attacker logs into a regular user account.
    2. Attacker identifies their own user ID.
    3. Attacker sends a crafted POST request to `modules/users/edit.php?id={own_id}` (which requires `index.php`).
    4. By including `role_id=1` (Admin) and `access_level_id=1` (Full) in the request, the attacker's own account is granted administrative privileges because the server fails to validate that a non-admin is attempting to assign restricted roles.
- **Impact:** A regular user can escalate themselves to a full System Administrator, gaining unauthorized access to all company data and administrative settings.
- **Remediation:**
    - Implement strict server-side validation in the Users module to ensure that only existing Admin users can modify the `role_id` or `access_level_id` fields.
    - Non-admin users should be prohibited from accessing the edit handler for the Users module entirely, or at minimum, the sensitive fields should be stripped from the update query if the actor is not an Admin.

---

## Finding 3: Unauthorized Access to Role Module Permissions

- **Status:** Remediated — `itm_is_admin()` guard on `modules/role_module_permissions/index.php`.
- **Severity:** High
- **Location:** `modules/role_module_permissions/index.php`
- **Attacker:** Any authenticated user.
- **Input:** Any POST request to the module handlers.
- **Attack Path:**
    1. Attacker logs into a regular user account.
    2. Attacker accesses `modules/role_module_permissions/index.php` directly.
    3. The module fails to perform any role-based access control (RBAC) checks.
    4. Attacker can list all defined permissions and modify them (e.g., granting their own role 'ALL' permissions) via the standard CRUD handlers.
- **Impact:** Unauthorized modification of system-wide security policies and permissions, leading to full access to all modules for the attacker's role.
- **Remediation:**
    - Add a mandatory administrative check at the top of `modules/role_module_permissions/index.php` to redirect or block non-admin users.
    - Example: `if (strtolower($_SESSION['role_name'] ?? '') !== 'admin') { die('Access denied'); }`

---

## Environment Stability

The full test suite was executed after verifying these vulnerabilities.
- **Command:** `php scripts/run_tests.php`
- **Result:** `OK (387 tests, 1415 assertions)` — re-run after each security deliverable for current counts.
- **Stability:** Confirmed. No regressions were introduced during the security audit.
