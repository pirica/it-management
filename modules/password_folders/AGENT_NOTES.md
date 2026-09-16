# AGENT_NOTES.md - Password Folders

## 1. Module Purpose

Flattened CRUD exposure for `password_folders` (per-employee vault folder tree). **Primary UX** remains `modules/passwords/` (vault lock, encryption, employee-scoped queries). This module is for registry/sidebar discovery and admin-style row maintenance — not a replacement for the vault UI.

## 2. Key Tables

- **password_folders** — hierarchical folders (`parent_id`) per `employee_id`

## 3. Required Relationships

- **password_folders** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **password_folders** → **employees** (`employee_id`, `ON DELETE CASCADE`)
- **password_folders** → self (`parent_id`, `ON DELETE SET NULL`)
- **password_entries** → **password_folders** (`folder_id`, `ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- **Private data — no audit trail:** no `audit_logs` / `trg_*_audit_*` on this table (see `AGENTS.md`).
- Canonical passwords module filters `WHERE employee_id = session employee`; this scaffold lists **all company rows** unless RBAC restricts access — treat as admin maintenance only.
- `employee_id` is required on every row; show as employee name in list/view, not raw ID.
- Hide `company_id` in UI.

## 5. UI Behavior Requirements

- Departments scaffold CRUD; `employee_id` and `parent_id` need FK label rendering.
- Vault encryption does not apply to folder **names** (plaintext `name` column).

## 6. API / AJAX

- `import_excel_rows` on `index.php`.
- CRUD record share wiring present from scaffold — vault snapshots may expose folder metadata.

## 7. Pitfalls

- Do not log folder names or structure to `audit_logs`.
- End users should use `modules/passwords/` for daily work; duplicate folder names allowed per unique key scope (`company_id`, `name`, `employee_id`, `id`).
