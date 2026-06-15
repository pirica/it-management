# AGENT_NOTES.md - Role Assignment Rights

## 1. Module Purpose
Defines which roles have the permission to assign other roles to users (e.g., "Admin can assign IT Staff").

## 2. Key Tables
- **role_assignment_rights** — mapping of role-to-role assignment permissions.

## 3. Required Relationships
- **role_assignment_rights** → depends on **companies**.
- **role_assignment_rights** → depends on **user_roles** (both for the source role and the target role).

## 4. Business Rules (Critical for Agents)
- **Unique Mapping**: Only one right definition per company, source role, and target role.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 12. Module Owner Notes (Optional)
Critical for delegated administration and security.
