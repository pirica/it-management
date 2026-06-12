# AGENT_NOTES.md - Role Module Permissions

## 1. Module Purpose
Manages granular access control for each module at the role level.

## 2. Key Tables
- **role_module_permissions** — stores view, create, edit, delete, import, and export rights per module and role.

## 3. Required Relationships
- **role_module_permissions** → depends on **companies**.
- **role_module_permissions** → depends on **user_roles**.

## 4. Business Rules (Critical for Agents)
- **Unique Constraint**: One permission set per company, role, and module name.
- **ALL Keyword**: The module name 'ALL' is often used to represent global permissions for a role.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM role_module_permissions WHERE role_id = ? AND module_name = ? AND company_id = ?");
$stmt->bind_param("isi", $roleId, $moduleName, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The heart of the RBAC (Role-Based Access Control) system.
