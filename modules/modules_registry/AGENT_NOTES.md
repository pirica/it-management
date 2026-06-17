# AGENT_NOTES.md - Modules Registry

## 1. Module Purpose
CRUD UI for the global **modules_registry** catalog (module slugs, display names, icons, active flag). Registry rows drive company module access and sidebar discovery (`itm_merge_registry_modules_into_sidebar_discovery()`).

## 2. Key Tables
- **modules_registry** — canonical module slug catalog (`module_slug`, `module_name`, `icon`, `active`, `is_system_module`).

## 3. Required Relationships
- **modules_registry** → referenced by **company_module_access** (`module_id`).
- Enforcement and sync helpers live in `includes/itm_company_module_access.php`.

## 4. Business Rules (Critical for Agents)
- Registry is global (not company-scoped on the table itself); access enforcement is per company via **company_module_access**.
- CRUD PHP is a **local copy** of the manufacturers flattened template (`$crud_table = 'modules_registry'`). Do **not** use `require __DIR__ . '/../manufacturers/…'` delegates.
- After manufacturers template changes, refresh with `itm_materialize_manufacturers_crud_module_files('modules_registry', true)` from CLI.

## 5. UI Behavior Requirements
- Standard flattened CRUD for registry rows.
- Sidebar listing for registry-only modules may appear before a folder exists; opening CRUD URLs still needs `modules/{slug}/` when linked from admin tools.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — full local CRUD files (materialized from `modules/manufacturers/`).

## 8. Multi-Tenant Rules
- Registry table is global; pair with **company_module_access** for tenant enablement.

## 10. Common Pitfalls
- Do not confuse this module folder with **company_module_access** (matrix UI for per-company toggles).
- Cross-module manufacturers requires are forbidden outside `modules/manufacturers/`.

## 12. Module Owner Notes (Optional)
Pairs with `modules/company_module_access/` for enforcement; see that module's `AGENT_NOTES.md` for matrix and AJAX rules.
