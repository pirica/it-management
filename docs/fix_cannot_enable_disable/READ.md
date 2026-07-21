# Fix for Seed Admin Role Association (Cannot Enable/Disable Share Modules)

## Issue
Administrators logged in to companies 2, 3, 4, and 5 (`Admin2` to `Admin5`) were unable to access `/modules/share_modules/index.php` or toggle any share module settings (enable/disable), being redirected to `dashboard.php`. This occurred because their `role_id`s in the database were `NULL`.

## Root Cause
The seed `employees` table insertion run at line 714 of `db/02_data.sql` sets `role_id` to `NULL` for all seed employees. The subsequent UPDATE query that associates seed admins to their company-specific `Admin` role (line 1330) was executed *before* the company 2-5 roles were replicated (which happens at the end of `db/02_data.sql` around line 1892). Thus, the UPDATE query failed to find role rows for companies 2-5 and only updated company 1's Admin.

## Fix
Move or append the role assignment UPDATE query to the end of `db/02_data.sql`, running it after all roles across all companies have been created and replicated.
