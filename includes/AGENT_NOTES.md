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

## 7. File Structure
- **alerts_visibility.php** — centralized visibility logic for global/private alerts.
- **notes_visibility.php** — owner + `shared_with_json` filter for Notes module.
- **todo_visibility.php** — global/assigned/creator filter for Todo module.
- **delete_functions.php** — shared logic for complex deletions (e.g., equipment).
- **companies_view_redirect.php** — legacy company view redirect; guarded via `itm_script_entry_guard.php`.
- **get_ports.php** / **update_port.php** — switch port AJAX endpoints; guarded + shared helpers in **switch_port_api_helpers.php**.
- **itm_script_entry_guard.php** — `itm_skip_http_entry_unless_direct()`, `itm_skip_view_partial_unless_context()`, PHPUnit processing detection.
- **switch_port_api_helpers.php** — shared lookup/VLAN helpers for port AJAX endpoints (avoids redeclare fatals during coverage).
- **itm_select_options_policy.php** — whitelist and blocked-table policy for `modules/select_options_api.php` quick-add inserts.
- **itm_api_rate_limit.php** — tier hourly limits; **Free** = unlimited, **no API key**, **session required** (`itm_api_resolve_rate_limit_row()` reads `$_SESSION['company_id']` + `$_SESSION['user_id']` when no key); `itm_api_tier_requires_api_key()`; probe payload `itm_api_build_rate_limit_probe_payload()` (`api_key_required`); `itm_api_enforce_rate_limit_or_exit()` for programmatic endpoints.
- **itm_company_module_access.php** — `has_module_access()`, `get_company_modules()`, `itm_list_all_modules_registry()`, `itm_ensure_registry_rows_for_module_slugs()`, `itm_enforce_module_access_or_exit()`, registry sync/seed helpers; loaded from `config/config.php`.
- **itm_it_location_linked_floor_plans.php** — IT Locations view partial; skips HTML when `$conn` / PHPUnit context missing.
- **employee_profile_photo.php** — employee profile photo paths under `files/{company_id}/Private/{username}_{employee_id}/profile/` (legacy `{username}_{user_id}` still served when `user_id` is set); upload (`emp_profile_photo_store_upload`), serve URL (`emp_profile_photo_url`), birthday display (`emp_format_birthday_display`, `emp_format_birthday_day_only`). Used by `modules/employees/` and `modules/birthdays/`.

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
