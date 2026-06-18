# Finding: RBAC Bypass in CRUD Modules

## Status (verified)

**Remediated for Expenses delete** — server-side `itm_require_role_module_permission(..., 'Expenses', 'delete')` in `modules/expenses/index.php` before CSRF/delete SQL. Regression: `php scripts/repro_rbac_bypass.php` (expects `[PASS]` with HTTP 403 and row retained).

**Scope:** Other modules may still rely on UI-only RBAC until the same guard is added to their POST handlers.

## Summary
The application's Role-Based Access Control (RBAC) system is only enforced at the UI level (hiding buttons) and is bypassed by direct POST requests to CRUD operation handlers.

## Attacker
Any authenticated user with a role that has restricted permissions (e.g., "Read Only").

## Input
Direct HTTP POST requests to module endpoints like `create.php`, `edit.php`, or `delete.php`.

## Path
1. Authenticate as a user with a "Read Only" role.
2. Observe that "Delete" buttons are hidden in the UI.
3. Submit a manual POST request to `modules/expenses/delete.php` with a valid `id` and `csrf_token`.
4. The backend processes the deletion without verifying if the user has the 'Delete' permission for that module.

## Location
modules/expenses/index.php

## Impact
Unauthorized users can create, modify, or delete sensitive data across various modules, leading to data loss and integrity violations.

## Remediation
Implement server-side authorization checks in the shared CRUD handler or the individual module wrappers. Use the `role_module_permissions` table to verify the user's rights before processing any data modification request.

**Expenses (done):** `itm_require_role_module_permission()` on the `$crud_action === 'delete'` block in `modules/expenses/index.php`.

**Repro script pitfalls (false FAIL):**
- `expenses.uq_expenses_company_scope` allows one row per `(company_id, cost_center_id)` — reusing `cost_center_id = 1` makes the seed insert fail; checking a missing `id` looks like a successful delete.
- Subprocess harnesses must not pre-declare `cr_require_valid_csrf_token()` — `index.php` defines it and PHP fatals on redeclare before the delete handler runs.
