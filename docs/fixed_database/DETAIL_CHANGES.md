# Detailed Changes - Vulnerability Remediation June 2026

## 1. Contacts Module IDOR Fix
- **File:** `modules/contacts/api/inline_edit.php`
- **Change:** Added `itm_require_admin($conn, $_SESSION['employee_id'])` check.
- **Reason:** Prevent non-administrative users from modifying contact details for other employees or departments.

## 2. Employee Sidebar Preferences RBAC Fix
- **File:** `modules/employee_sidebar_preferences/index.php`
- **Change:** Added `itm_require_crud_role_module_permission($conn, $crud_action, 'employee_sidebar_preferences')` to the `edit` POST handler.
- **Reason:** Ensure that only users with 'edit' permissions for the module can modify sidebar preferences. (The 'delete' action already had this guard).
