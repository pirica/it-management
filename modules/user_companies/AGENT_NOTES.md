# AGENT_NOTES.md - User Companies

## 1. Module Purpose
Mapping table that defines which companies a specific user has access to in this multi-tenant system.

## 2. Key Tables
- **user_companies** — links users to companies.

## 3. Required Relationships
- **user_companies** → depends on **users**.
- **user_companies** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Primary Link:** determines the company selection list on login/dashboard.
- Module browser QA skips some write steps — read-only / shared auth constraints.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Cross-tenant by design: one user may map to many companies; still validate `company_id` on each row.

## 12. Module Owner Notes (Optional)
Critical for access control in a multi-tenant environment.
