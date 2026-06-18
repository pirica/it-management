# AGENT_NOTES.md - Includes

## 1. Module Purpose
Contains shared PHP logic, helper functions, and visibility filters used across multiple modules.

## 2. Key Tables
- Primarily provides helper logic for **alerts**, **equipment**, and **audit_logs**.

## 3. Required Relationships
- Functions here often depend on `config/config.php`.

## 4. Business Rules (Critical for Agents)
- **Visibility Helpers**: `alerts_visibility.php` is mandatory for all alert-related queries.
- **Security Functions**: Use `itm_is_safe_identifier` for dynamic SQL identifiers.
- **Manufacturers CRUD template (`ui_config.php`):** Only `modules/manufacturers/` may host the live template. Other modules get **copied** PHP via `itm_materialize_manufacturers_crud_module_files()` / `itm_auto_create_module_scaffold()` — never `require __DIR__ . '/../manufacturers/…'`. New auto-scaffolded sidebar entries use **⚠️** (`itm_sidebar_auto_scaffolded_module_emoji()`). Legacy delegate stub folders are removed by QA cleanup (`itm_remove_manufacturers_template_scaffold_module_dirs()`).

## 7. File Structure
- **alerts_visibility.php** — centralized visibility logic for global/private alerts.
- **notes_visibility.php** — owner + `shared_with_json` filter for Notes module; `itm_notes_fetch_visible_by_id()` for single-record view/edit load; `itm_notes_private_images_dir()`, `itm_notes_normalize_image_filename()`, and `itm_notes_resolve_image_path()` confine note attachment ZIP downloads to `files/{company_id}/Private/{username}_{user_id}/notes/`; `itm_notes_json_mutation_response()` standardises AJAX mutation JSON (404 when zero rows affected).
- **itm_api_json_response.php** — shared `itm_api_json_response()` and `itm_api_mutation_requires_rows()` for Org Chart, Rack Planner, and switch-port AJAX endpoints.
- **itm_maintenance_script_admin_gate.php** — `itm_enforce_maintenance_script_admin_browser()` restricts browser access to maintenance/security scripts to Admin users (CLI unchanged).
- **todo_visibility.php** — global/assigned/creator filter for Todo module.
- **delete_functions.php** — shared logic for complex deletions (e.g., equipment).
- **companies_view_redirect.php** — legacy company view redirect; guarded via `itm_script_entry_guard.php`.
- **get_ports.php** / **update_port.php** — switch port AJAX endpoints; guarded + shared helpers in **switch_port_api_helpers.php**.
- **itm_script_entry_guard.php** — `itm_skip_http_entry_unless_direct()`, `itm_skip_view_partial_unless_context()`, PHPUnit processing detection.
- **switch_port_api_helpers.php** — shared lookup/VLAN helpers for port AJAX endpoints (avoids redeclare fatals during coverage).
- **itm_select_options_policy.php** — whitelist and blocked-table policy for `modules/select_options_api.php` quick-add inserts.
- **itm_users_sensitive_fields.php** — canonical Users list/view column denylist (`password`, `vault_key_hash`, reset-token fields); `itm_users_filter_ui_columns()` strips them from `$uiColumns`.
- **itm_api_rate_limit.php** — tier hourly limits; **Free** = unlimited, **no API key**, **session required** (`itm_api_resolve_rate_limit_row()` reads `$_SESSION['company_id']` + `$_SESSION['user_id']` when no key); `itm_api_tier_requires_api_key()`; probe payload `itm_api_build_rate_limit_probe_payload()` (`api_key_required`); `itm_api_enforce_rate_limit_or_exit()` for programmatic endpoints.
- **itm_company_module_access.php** — `has_module_access()`, `get_company_modules()`, `itm_list_all_modules_registry()`, `itm_ensure_registry_rows_for_module_slugs()`, `itm_sidebar_discovery_probe_cleanup()`, `itm_enforce_module_access_or_exit()`, registry sync/seed helpers; loaded from `config/config.php`.
- **itm_role_module_permissions.php** — `itm_resolve_active_company_id()`, `itm_user_has_role_module_permission()`, `itm_require_role_module_permission()` for server-side RBAC checks against `role_module_permissions`; loaded from `config/config.php`.
- **ui_config.php** — sidebar discovery; manufacturers CRUD materialization (`itm_materialize_manufacturers_crud_module_files()`, `itm_auto_create_module_scaffold()`); ⚠️ prefix for newly scaffolded tables (`itm_sidebar_auto_scaffolded_module_emoji()`); legacy delegate detection (`itm_module_php_file_delegates_to_manufacturers_module()`); QA cleanup (`itm_remove_manufacturers_template_scaffold_module_dirs()`).
- **itm_date_format.php** — UK dd/mm/yyyy parse/display helpers (`itm_parse_date_input`, `itm_format_date_display`, `itm_format_cell_scalar_display`); loaded from `config/config.php`. Excel import uses `itm_normalize_sql_date_literal()` in `config.php`.
- **employee_profile_photo.php** — employee profile photo paths under `files/{company_id}/Private/{username}_{employee_id}/profile/` (legacy `{username}_{user_id}` still served when `user_id` is set); upload (`emp_profile_photo_store_upload`), serve URL (`emp_profile_photo_url`), birthday display (`emp_format_birthday_display`, `emp_format_birthday_day_only`). Used by `modules/employees/` and `modules/birthdays/`.
- **itm_profile_photo_upload.php** — shared PNG/JPG resolver (`itm_profile_photo_allowed_extension`) using `getimagesize()`, finfo MIME, browser `type`, and filename fallback; used by `modules/private_contacts/includes/private_contact_photo.php`.
- **fk_dropdown_helpers.php** — tenant FK label resolution (`itm_fk_label_column_for_table()` prefers `name_type` for `employee_type`); business-key remap via `itm_fk_resolve_company_equivalent_id()`.
- **itm_system_status_native.php** — PHP/MySQL + Linux hardware JSON for `scripts/system_status_api.php` (`itm_system_status_native_payload()`, `/proc` + mysqli).
- **itm_system_status_storage.php** — Explorer/upload directory sizes and active-database table metrics for System Status tabs.
- **itm_system_status_powershell.php** — Windows hardware runner: `shell_exec` availability, `.ps1` readability, `itm_system_status_run_powershell_action()`.

## 8. Multi-Tenant Rules
- Visibility helpers always take `company_id` / user context from caller; never bypass tenant filters in shared helpers.

## 11. Examples of Safe Code Patterns

### Using Alerts Visibility SQL
```php
require_once ROOT_PATH . 'includes/alerts_visibility.php';
$visibility = itm_alerts_visibility_sql('alias');
```

## 12. Module Owner Notes (Optional)
Centralized logic here prevents code duplication and ensures consistent security/visibility enforcement.
