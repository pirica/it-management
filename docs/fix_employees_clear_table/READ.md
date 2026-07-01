# Bug Fix: Employees "Clear Table" Referential Integrity Failure

## Problem Statement
The "Clear Table" action in the Employees module was implemented using a single bulk `DELETE` operation. This approach failed with a Foreign Key constraint violation (MySQL errno 1451) whenever an employee had associated data in other modules (e.g., **Passwords**, **Bookmarks**, **Private Contacts**, or **To-Do** categories).

Because these dependencies are managed at the application level rather than through `ON DELETE CASCADE` in the database schema, a standard bulk delete is insufficient for maintaining integrity.

## Fix Implementation
The `employees_clear_table_for_company()` function has been rewritten to adopt an iterative deletion strategy.

1.  **ID Collection:** It first retrieves all applicable employee IDs into an array using a prepared statement.
2.  **Iterative Deletion:** It iterates through these IDs and calls the specialized `employees_delete_record()` helper for each one.
3.  **Dependency Handling:** By reusing the existing single-delete logic, the system correctly detaches or removes all inbound references (e.g., clearing password entries and bookmarks) before removing the main employee row.
4.  **Security:** Uses MySQLi prepared statements to ensure protection against SQL injection.

This ensures that the "Clear Table" action works reliably even for real-world tenants with extensive employee data.

## Documentation Artifacts
- **Fixed File:** `/docs/fixed_files_vulnerability_employees_clear_table/fixed_files/delete_clear_table.php` (The updated logic).
- **Validation Script:** `/docs/fixed_files_vulnerability_employees_clear_table/scripts/verify_clear_table_fix.php` (Proof of fix).
- **Auto-fix Generator:** `/docs/fix_employees_clear_table/auto_fix_vuln.php` (The script that generated the fixed file).
- **Script Registry:** `/docs/fixed_files_vulnerability_employees_clear_table/scripts/scripts.php` (Catalog of fix-related tools).

## Verification
To verify the fix in the current environment, run the provided script:
```bash
php docs/fixed_files_vulnerability_employees_clear_table/scripts/verify_clear_table_fix.php
```
This script creates a test employee with a dependent bookmark and confirms that the new logic successfully clears both records.
