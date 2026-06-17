## 1. Module Purpose

Admin-only module that manages per-company module visibility. Administrators use a matrix UI to enable or disable modules for each company. The matrix lists every registry row, including hidden, inactive, sidebar-excluded, and system modules.

## 2. Key Tables

- `modules_registry` — global catalog of module slugs and display names
- `company_module_access` — per-company enabled/disabled flags (`enabled` tinyint)

## 3. Required Relationships

- `company_module_access.company_id` → `companies.id`
- `company_module_access.module_id` → `modules_registry.id`
- Enforcement helpers live in `includes/itm_company_module_access.php` and run from `config/config.php`

## 4. Business Rules (Critical for Agents)

- Only `itm_is_admin()` users may access this module.
- Opt-in policy: `has_module_access()` requires `company_module_access.enabled = 1` plus active registry row; missing row denies access. Fresh installs seed all company × module rows in `database.sql`.
- The admin matrix must show **all** registry modules — never filter rows like the sidebar does.
- System modules appear in the matrix; `settings` stays always available to all users.
- Inactive registry rows (`active = 0`) are listed but toggles are disabled.
- AJAX toggles must use CSRF and write through `itm_set_company_module_access()`.

## 5. UI Behavior Requirements

- `index.php` — company × module matrix with AJAX checkboxes and ✅/❌ indicators (`1` = ✅, `0` = ❌ only; never ✓/✗), Select All / Cancel Select / Unselect All, client-side filter.
- `list_all.php` — flat registry list with search.
- `create.php` / `edit.php` — registry row CRUD with checkbox pattern for `active` and `is_system_module`.
- Standard layout shell with sidebar/header.

## 6. API Actions (If Applicable)

- `ajax_action=toggle_access` — POST JSON toggle for one `(company_id, module_id)` pair.
- `ajax_action=bulk_toggle_access` — POST JSON bulk toggle via `pairs_json`.

## 7. File Structure

- `index.php` — matrix UI + AJAX handlers + registry forms by `$crud_action`
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — wrappers setting `$crud_action`
- `js/company-module-access-matrix.js` — matrix selection and AJAX client

## 8. Multi-Tenant Rules

- Registry is global; access rows are per `company_id`.
- Matrix columns use all active companies; enforcement uses session `company_id`.

## 9. Audit Logging Requirements

- DB triggers on `modules_registry` and `company_module_access`.
- PHP `itm_log_audit()` on registry CRUD and via `itm_set_company_module_access()` when `enable_audit_logs` is on.

## 10. Common Pitfalls

- Do not hide disabled modules from the admin matrix.
- Do not add per-module `has_module_access()` guards — central enforcement in `config/config.php` handles URL access.
- Run `php scripts/sync_modules_registry.php` after adding new module folders.

## 11. Examples of Safe Code Patterns

```php
if (!itm_is_admin($conn, (int)($_SESSION['user_id'] ?? 0))) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

itm_set_company_module_access($conn, $companyId, $moduleId, $enabled);
```

## 12. Module Owner Notes (Optional)

- Complements `role_module_permissions` (role CRUD flags) with a company-level visibility gate that runs first.
