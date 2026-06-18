# Finding: Multi-Tenant Data Leak in Users Module

## Summary
Tenant administrators can view users from all other companies in the system due to a lack of `company_id` scoping in the `users` module's list query.

## Attacker
An authenticated user with the "Admin" role within their own company (Tenant Admin).

## Input
Accessing the `modules/users/index.php` page.

## Path
1. Log in as an Admin for "Company A".
2. Navigate to the Users module.
3. The SQL query for listing users does not include a `WHERE company_id = ?` clause for Admin users, resulting in a list of all users in the system database.

## Location
modules/users/index.php

## Impact
Massive disclosure of PII (usernames, emails, names) across all tenants, violating data isolation and privacy requirements.

## Remediation
Modify the SQL query in `modules/users/index.php` to always include a filter for the current user's `company_id`, ensuring that even Administrators can only see users within their own organization.
