# Security Audit Report - June 2026

This report summarizes the findings of a scheduled application-security review.

## Summary of Findings

| ID | Title | Severity | Location |
|----|-------|----------|----------|
| 1 | Explorer Path Validation Bypass via `./` Prefix | High | `modules/explorer/api.php` |
| 2 | Sensitive Data Leak in Authentication Attempt Logging | Medium | `login.php` |
| 3 | Unauthorized Entity Creation via Select Options API | Medium | `includes/itm_select_options_policy.php` |
| 4 | Zip Slip Vulnerability in Explorer Unzip | High | `modules/explorer/api.php` |

---

## 1. Explorer Path Validation Bypass via `./` Prefix

- **Severity:** High
- **Location:** `modules/explorer/api.php` (also affects `modules/explorer/file.php`)
- **Description:** The `get_full_path` function uses `str_starts_with($relative_path, 'Private/')` to enforce access control. An attacker can bypass this check by prefixing the path with `./` (e.g., `./Private`). This allows any authenticated user to access the `Private` or `Departments` root folders, and read other users' private files.
- **Attacker:** Any authenticated user.
- **Input Controlled by Attacker:** The `path` parameter in POST requests to `modules/explorer/api.php` or the `path` parameter in GET requests to `modules/explorer/file.php`.
- **Attack Path:**
    1. Attacker logs in.
    2. Attacker sends a request to Explorer API with `path=./Private`.
    3. The code checks if `./Private` starts with `Private/` (it doesn't).
    4. The code checks if `./Private` is exactly `Private` (it isn't).
    5. Access control is bypassed, and the full path is resolved to the Private root.
- **Impact:** Unauthorized access to sensitive company and user files.
- **Remediation:** Normalize the path using `realpath()` or a custom segment-based normalizer before performing prefix checks.

---

## 2. Sensitive Data Leak in Authentication Attempt Logging

- **Severity:** Medium
- **Location:** `login.php`, `forgot-password.php`
- **Description:** The application records authentication attempts in the `attempts` table. It stores the user-provided identifier (email or username) in plaintext. If a user accidentally types their password into the email field, it is saved in the database.
- **Attacker:** Any user with access to the `Attempts` module (typically administrators, but potentially others if RBAC is weak).
- **Input Controlled by Attacker:** The `email` field on the login and forgot-password pages.
- **Attack Path:**
    1. User accidentally types password in the "Email or Username" field and clicks Login.
    2. `login.php` calls `itm_record_login_attempt` with the password as the identifier.
    3. The password is saved in the `email` column of the `attempts` table.
- **Impact:** Disclosure of plaintext passwords in logs/database.
- **Remediation:** Sanitize or mask the identifier if it doesn't look like a valid email or username, or avoid logging the full identifier for failed attempts.

---

## 3. Unauthorized Entity Creation via Select Options API

- **Severity:** Medium
- **Location:** `includes/itm_select_options_policy.php`
- **Description:** The `select_options_api.php` endpoint allows quick-adding records to whitelisted lookup tables. The `companies` table is currently in the whitelist, allowing any authenticated user to create new companies.
- **Attacker:** Any authenticated user.
- **Input Controlled by Attacker:** POST parameters `table`, `label_col`, and `new_value`.
- **Attack Path:**
    1. Attacker logs in as a regular user.
    2. Attacker sends a POST request to `modules/select_options_api.php` with `table=companies`.
    3. The system checks the whitelist and allows the insertion.
- **Impact:** Unauthorized creation of top-level system entities (companies), potentially leading to resource exhaustion or database clutter.
- **Remediation:** Remove `companies` from `itm_select_options_allowed_tables()` in `includes/itm_select_options_policy.php`.

---

## 4. Zip Slip Vulnerability in Explorer Unzip

- **Severity:** High
- **Location:** `modules/explorer/api.php`
- **Description:** The `unzip` action uses `$zip->extractTo($dir)` without validating the paths of the entries within the ZIP file. A malicious ZIP file containing entries with traversal components (e.g., `../../shell.php`) can overwrite files outside the intended extraction directory.
- **Attacker:** Any authenticated user with permission to upload and unzip files.
- **Input Controlled by Attacker:** Contents of the uploaded ZIP file.
- **Attack Path:**
    1. Attacker creates a ZIP file containing a file named `../../../poc.txt`.
    2. Attacker uploads and unzips the file using the Explorer module.
    3. The file is extracted to the project root or other sensitive locations.
- **Impact:** Potential Remote Code Execution (RCE) if the attacker can overwrite PHP files or `.htaccess` files.
- **Remediation:** Manually iterate through ZIP entries and validate that the resolved destination path is within the target directory before extracting.
