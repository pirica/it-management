# Finding: RBAC Bypass in CRUD Modules

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
