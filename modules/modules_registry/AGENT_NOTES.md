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


## 5. UI Behavior Requirements
- Standard flattened CRUD for registry rows.
- Sidebar listing for registry-only modules may appear before a folder exists; opening CRUD URLs still needs `modules/{slug}/` when linked from admin tools.
- `module_slug` is the canonical key merged by `itm_sidebar_structure()` and **company_module_access**.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import when enabled on flattened index.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — full local CRUD files.

## 8. Multi-Tenant Rules
- Registry table is global; pair with **company_module_access** for tenant enablement.

## 9. Audit Logging Requirements
- `trg_modules_registry_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Do not confuse this module folder with **company_module_access** (matrix UI for per-company toggles). [Cursor-Valid]


## 11. Examples of Safe Code Patterns

### Safe registry lookup by slug
```php
$stmt = $conn->prepare('SELECT id, module_name, icon, active FROM modules_registry WHERE module_slug = ? LIMIT 1');
$stmt->bind_param('s', $slug);
```

## 12. Module Owner Notes (Optional)
Pairs with `modules/company_module_access/` for enforcement; see that module's `AGENT_NOTES.md` for matrix and AJAX rules.
