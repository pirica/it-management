# AGENT_NOTES.md - Registration Invitations

## 1. Module Purpose
Manages invitations for new users to register on the platform.

## 2. Key Tables
- **registration_invitations** — stores invitation tokens and recipient details.

## 3. Required Relationships
- **registration_invitations** → depends on **companies**.
- **registration_invitations** → depends on **users** (the sender).

## 4. Business Rules (Critical for Agents)
- **Tokens**: Uses unique, time-limited tokens for registration.
- **Usage**: Once an invitation is used to create an account, it should be marked as used or deleted.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Resend**: Option to resend the invitation email.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 12. Module Owner Notes (Optional)
Controlled onboarding for new system users.
