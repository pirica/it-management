# Finding: Broken Access Control in User Companies Module

## Summary
The `user_companies` module, which manages sensitive cross-tenant mappings, is accessible to all authenticated users regardless of their administrative status.

## Attacker
Any authenticated regular user.

## Input
Accessing `modules/user_companies/index.php`.

## Path
1. Log in as a regular user.
2. Directly navigate to `modules/user_companies/index.php`.
3. The module loads successfully, exposing administrative data and allowing unauthorized management of company assignments.

## Location
modules/user_companies/index.php

## Impact
Unauthorized users can view and potentially manipulate user-to-company assignments, facilitating further privilege escalation or data access across tenants.

## Remediation
Add `itm_require_admin($conn, $_SESSION['user_id'] ?? 0);` to the top of `modules/user_companies/index.php` to ensure only system administrators can access this sensitive module.
