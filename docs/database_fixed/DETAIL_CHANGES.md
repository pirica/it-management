# Database Fixes Summary

## Changes Implemented

### 1. IDFs API Fix (RBAC)
- While primarily a code fix, ensure that the `modules_registry` contains the correct `module_slug` ('idfs') and `module_name` ('Idfs') for the RBAC check to function correctly.
- Ensure `role_module_permissions` table is populated for roles that should access these APIs.

### 2. Floor Designer Fixes (SQLi & RCE)
- The SQLi fix is code-based (sanitization).
- The RCE fix ensures that files stored in the database's `floor_plans` table and on the filesystem have validated extensions.

## Execution
Run the application bootstrap to ensure all migrations are applied, or manually verify the registry entries using the provided validation scripts.
