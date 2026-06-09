# AGENT_NOTES.md - User Companies

## 1. Module Purpose
Mapping table that defines which companies a specific user has access to in this multi-tenant system.

## 2. Key Tables
- **user_companies** — links users to companies.

## 3. Required Relationships
- **user_companies** → depends on **users**.
- **user_companies** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Primary Link**: This is what determines the company selection list on the login/dashboard.

## 8. Multi-Tenant Rules
- **Cross-Tenant**: This table is one of the few that spans multiple companies for a single user.

## 12. Module Owner Notes (Optional)
Critical for access control in a multi-tenant environment.
