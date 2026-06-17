# Security Audit Report - June 2026

This report summarizes a subset of critical and high-severity security vulnerabilities from the scheduled application-security review.

**Full catalog:** all 15 June 2026 findings (14 remediated, 1 deferred git-reset) are listed in [`VULNERABILITY_SUMMARY.md`](VULNERABILITY_SUMMARY.md) and [`app---flagged-vulnerabilities.json`](app---flagged-vulnerabilities.json). Per-finding detail lives in [`vulnerability_report_*.md`](vulnerability_report_select_api.md).

## Summary of Findings (abbreviated — first three reviewed in depth)

| ID | Title | Severity | Location | Status |
|---|---|---|---|---|
| 1 | Authenticated Remote Code Execution (RCE) via File Upload | Critical | `modules/explorer/api.php` | Remediated |
| 2 | Privilege Escalation via User Profile Modification | High | `modules/users/index.php` | Remediated |
| 3 | Unauthorized Access to Role Module Permissions | High | `modules/role_module_permissions/index.php` | Remediated |

---

## Finding 1: Authenticated Remote Code Execution (RCE) via File Upload

- **Status:** Remediated — blocked executable extensions on upload; `deny_http` hardening on `files/` tree via `itm_ensure_files_storage_directory()`. Regression: `php scripts/verify_explorer_rce_htaccess.php`.
- **Severity:** Critical (historical)
- **Location:** `modules/explorer/api.php`
- **Attacker:** Any authenticated user (historical).
- **Impact:** Complete system compromise via uploaded executable content under `files/{company_id}/`.
- **Remediation (applied):** Extension block on upload; `deny_http` managed `.htaccess` on every `files/` segment; serve assets through `modules/explorer/file.php`.

---

## Finding 2: Privilege Escalation via User Profile Modification

- **Status:** Remediated — `itm_require_admin()` on all Users entry points; non-admins cannot set `role_id` or `access_level_id`.
- **Severity:** High (historical)
- **Location:** `modules/users/index.php`
- **Attacker:** Any authenticated user (historical).
- **Impact:** Regular users could escalate to full administrator.
- **Remediation (applied):** Admin-only access to Users module; privilege fields stripped for non-admins on update.

---

## Finding 3: Unauthorized Access to Role Module Permissions

- **Status:** Remediated — `itm_is_admin()` guard on `modules/role_module_permissions/index.php`.
- **Severity:** High (historical)
- **Location:** `modules/role_module_permissions/index.php`
- **Attacker:** Any authenticated user (historical).
- **Impact:** Unauthorized modification of system-wide RBAC policies.
- **Remediation (applied):** Mandatory admin check on module entry.

---

## Environment Stability

The full test suite was executed after security deliverables.
- **Command:** `php scripts/run_tests.php`
- **Result:** `OK (389 tests, 1420 assertions)` — re-run after each security deliverable for current counts.
- **Stability:** Confirmed. No regressions were introduced during the security audit.
