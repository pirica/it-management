# Fix for Sidebar Preferences RBAC Vulnerability

This directory contains the automation logic to fix a missing Role-Based Access Control (RBAC) check in the Employee Sidebar Preferences module.

## Vulnerability Detail
The `modules/employee_sidebar_preferences/index.php` file implemented an RBAC check for the 'delete' action but failed to do so for the 'edit' (create/update) action. This allowed users with access to the module to modify preferences for any employee.

## Fix Logic
The `auto_fix_vuln.php` script adds a call to `itm_require_crud_role_module_permission($conn, $crud_action, 'employee_sidebar_preferences')` within the POST handler for the 'edit' action.

## Usage
Run `php docs/fix_user_sidebar_preferences/auto_fix_vuln.php` to generate the patched file.
