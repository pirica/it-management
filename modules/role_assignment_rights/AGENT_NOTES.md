# AGENT_NOTES.md - Role Assignment Rights

## 1. Module Purpose
Defines which roles may assign other roles to users (e.g. Admin → IT Staff).

## 2. Key Tables
- **role_assignment_rights** — source role → assignable target role.

## 3. Required Relationships
- **role_assignment_rights** → **companies**, **user_roles** (source and target).

## 4. Business Rules (Critical for Agents)
- **Unique mapping:** one row per company + source role + target role.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 12. Module Owner Notes (Optional)
Critical for delegated administration and security.
