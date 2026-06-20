# Security Audit Report - June 2026

This report summarizes the findings of a scheduled application-security review and the remediation shipped on the security-audit branch.

## Summary of Findings

| ID | Title | Severity | Location | Status |
|----|-------|----------|----------|--------|
| 1 | Explorer Path Validation Bypass via `./` Prefix | High | `modules/explorer/api.php`, `modules/explorer/file.php` | **Fixed** — segment normalization via `includes/itm_explorer_paths.php` |
| 2 | Sensitive Data Leak in Authentication Attempt Logging | Medium | `login.php`, `forgot-password.php` | **Fixed** — `itm_normalize_login_attempt_identifier()` redacts non-email/username input |
| 3 | Unauthorized Entity Creation via Select Options API | Medium | `includes/itm_select_options_policy.php` | **Fixed** — `companies` moved to blocked tables |
| 4 | Zip Slip Vulnerability in Explorer Unzip | High | `modules/explorer/api.php` | **Fixed** — `explorer_extract_zip_safely()` validates entry paths before write |

---

## 1. Explorer Path Validation Bypass via `./` Prefix

- **Severity:** High
- **Location:** `modules/explorer/api.php` (also affects `modules/explorer/file.php`)
- **Description:** The `get_full_path` function used prefix checks on raw paths. A `./Private` value bypassed `Private/` ACL checks.
- **Remediation (applied):** `explorer_normalize_relative_path()` collapses `.` segments before ACL checks in `get_full_path()` and `file.php`.
- **Regression:** `php scripts/test_explorer_paths.php`, `php scripts/repro_explorer_path_bypass_v4.php`, PHPUnit `ExplorerPathBypassTest`.

---

## 2. Sensitive Data Leak in Authentication Attempt Logging

- **Severity:** Medium
- **Location:** `login.php`, `forgot-password.php`
- **Description:** Failed login stored the raw email/username field, which could persist a mistyped password.
- **Remediation (applied):** `includes/itm_login_attempt_identifier.php` stores valid emails/usernames verbatim and replaces other input with `[redacted:{sha256-prefix}]` for both logging and rate-limit keys.
- **Regression:** `php scripts/repro_attempts_data_leak_v2.php`, PHPUnit `AttemptsDataLeakTest`.

---

## 3. Unauthorized Entity Creation via Select Options API

- **Severity:** Medium
- **Location:** `includes/itm_select_options_policy.php`
- **Description:** `companies` was whitelisted for dropdown quick-add, allowing any authenticated user to insert company rows.
- **Remediation (applied):** `companies` removed from `itm_select_options_allowed_tables()` and added to `itm_select_options_blocked_tables()`.
- **Regression:** `php scripts/repro_select_options_unauthorized_v2.php`, `php scripts/verify_select_options_escalation.php`, PHPUnit `SelectOptionsBypassTest`.

---

## 4. Zip Slip Vulnerability in Explorer Unzip

- **Severity:** High
- **Location:** `modules/explorer/api.php`
- **Description:** `ZipArchive::extractTo()` accepted traversal entry names such as `../../../poc.txt`.
- **Remediation (applied):** `explorer_extract_zip_safely()` extracts entries only when the resolved destination remains under the target directory.
- **Regression:** `php scripts/repro_explorer_zip_slip_v2.php`, PHPUnit `ExplorerZipSlipTest`.
