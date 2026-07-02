# Database Fixes for Identified Vulnerabilities

## role_module_permissions
- Added granular checks to ensure users cannot manipulate permissions without authorization.
- Enforced company scoping on all permission queries.

## employees
- Hardened contact update logic via AJAX to prevent IDOR attacks.
