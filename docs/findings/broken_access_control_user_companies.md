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
`modules/user_companies/index.php`, `edit.php`, `view.php`, `list_all.php` (and `delete.php` via `index.php`). This module has no standalone `create.php`; create flows use `index.php`.

## Impact
Unauthorized users can view and potentially manipulate user-to-company assignments, facilitating further privilege escalation or data access across tenants.

## Remediation
Call `itm_require_admin($conn, $_SESSION['user_id'] ?? 0);` immediately after `config.php` on **every** entry file (`index.php`, `edit.php`, `view.php`, `list_all.php`). `delete.php` and create flows route through `index.php`, which carries the gate.
