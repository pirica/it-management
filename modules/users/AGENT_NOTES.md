# AGENT_NOTES.md - Users

## 1. Module Purpose
Manages the system users, including credentials, role assignments, and vault keys.

## 2. Key Tables
- **users** — main user account data.

## 3. Required Relationships
- **users** → depends on **companies** (Primary company).
- **users** → depends on **user_roles**.

## 4. Business Rules (Critical for Agents)
- **Security**: Passwords must be hashed. Master keys for the password vault must be handled securely.
- **Role Assignment**: Users can only assign roles they have the "assignment rights" for.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Password Reset**: Secure flow for changing credentials.

## 8. Multi-Tenant Rules
- A user is primarily linked to one company but can have access to multiple via `user_companies`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 12. Module Owner Notes (Optional)
The core identity module.
