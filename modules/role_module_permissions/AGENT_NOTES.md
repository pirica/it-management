# AGENT_NOTES.md - Role Module Permissions

## 1. Module Purpose
Granular RBAC: view/create/edit/delete/import/export per module and role.

## 2. Key Tables
- **role_module_permissions** — permission flags per role and module name.

## 3. Required Relationships
- **role_module_permissions** → **companies**, **user_roles**.

## 4. Business Rules (Critical for Agents)
- **Unique constraint:** one permission set per company + role + module name.
- **`ALL` module name:** global permissions for a role when module name is `ALL`.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM role_module_permissions WHERE role_id = ? AND module_name = ? AND company_id = ?");
$stmt->bind_param("isi", $roleId, $moduleName, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Heart of the RBAC system.
