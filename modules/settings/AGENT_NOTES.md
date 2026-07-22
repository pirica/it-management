# AGENT_NOTES.md - Settings

## 1. Module Purpose
Central hub for system-wide configuration, UI customization, sidebar management, and database maintenance/backups.

## 2. Key Tables
- **ui_configuration** — stores UI element positioning, pagination, favicon, per-user `module_icon_overrides` JSON, `equipment_type_sidebar_visibility` JSON, and per-user API key / rate-limit metadata. **Does not** store sidebar section/item order or show-hide flags (legacy columns removed at runtime).
- **employee_sidebar_preferences** — canonical SideMenu layout per `company_id` + `employee_id`: `entry_type` (`section`/`item`), `entry_id`, `section_id`, `display_order`, **`is_visible`**. Written by `itm_save_employee_sidebar_preferences()` from Settings **save_ui_config** and `user-config.php` **update_sidebar**.
- **equipment_types** — (partially managed here for icons/emojis).

## 3. Required Relationships
- Scoped by **companies**.
- Sidebar preferences linked to **users**.
- API keys are stored per `company_id` + `employee_id` on `ui_configuration`.

## 4. Business Rules (Critical for Agents)
- **UI Persistence**: Changes to button positions or pagination must call `collectAndSetHiddenFields()` in the UI. Fresh-import / column defaults for `table_actions_position`, `new_button_position`, `export_buttons_position`, and `back_save_position` are **`left`** (`db/`, `itm_ui_config_defaults()`); Settings dropdown labels mark **Left (default)**.
- **API Access card**: **Free** tier — no API key UI; copy states **signed-in session** is required for programmatic access and for `scripts/api.php?rate_limit=1` without `api_key`. **Paid** tiers — only `api_key` is editable (save or generate). `tier` is a **blocked** `<select>`; rate-limit counters are read-only. POST `save_api_key` / `generate_api_key` rejected on Free tier.
- **Database Maintenance**: Allows triggering schema verification and table repairs.
- **Backup/Restore**: Handles SQL dump generation and manual SQL imports. **All Backups** table (`#all-backups`) uses `table-tools.js` export/import plus client-side row filter; server-side column sort via `?sort=name|size|modified&dir=ASC|DESC` (default `modified` `DESC`) with ▲/▼ on **File Name**, **Size (KB)**, and **Last Modified (UTC)**. **Options** column is first and not sortable.

## 5. UI Behavior Requirements
- **ui_configuration reviewed gate:** gate-excluded in `scripts/data/ui_configuration_excluded_modules.txt`; intentional gaps (SideMenu drag-reorder table, no server search/pagination on audited first table, no CRUD entry files, All Backups client filter + server sort on `#all-backups` only) documented in `scripts/data/ui_configuration_reviewed.json` — audit lines print `[n/a][pass|fail|n/a][reviewed]`.
- **Sidebar Toggles**: SideMenu **Show** checkboxes use `itm_sidebar_item_effective_visible()` / `itm_sidebar_section_effective_visible()` with session `employee_id` so **`employee_roles.sidebar_show`** matches live sidebar behaviour. Layout save passes the same employee id into `itm_sidebar_prepare_layout_config_for_save()` for section sync.
- **Sidebar emoji overrides**: SideMenu module rows render in a compact table (`Show` | `Icon` | `Module` | `Order`) with `.itm-module-icon-input` in the icon column; matching the company default on save clears `module_icon_overrides`. `dashboard_link` uses catalog emoji fallback (`📈`) because it has `match_page` only (icon key `dashboard` on save).
- **Emoji Equipment Type Sidebar**: hidden when the signed-in employee lacks `has_module_access(..., 'equipment')`; save preserves existing `equipment_type_sidebar_visibility` when the block is not rendered. Checkbox **checked** state uses `itm_equipment_type_sidebar_effective_visible()` (same `employee_roles.sidebar_show` override as SideMenu when a type is hidden in prefs but RBAC allows equipment).
- **System flags layout**: **All roles** — `enable_chatbot` only (`ui_configuration` / schema default **1**). **System (Admin Role only)** — `enable_all_error_reporting`, `enable_audit_logs`, `enable_auto_scaffolding` (admin UI + POST save via `itm_is_admin()`; non-admins keep existing values on save).
- **Favicon/SQL Uploads**: Supports drag-and-drop file uploads for favicon and SQL backup files.
- **Favicon preview**: On load, `itm_ui_config_sync_favicon_path_from_disk()` backfills empty `favicon_path` when `images/favicons/company_{company_id}.ico` already exists (uploaded file without DB path). Preview and tab icon use `itm_ui_config_resolve_favicon_relative_path()` + `itm_ui_config_favicon_url($config, $company_id)`.
- **API key POST actions**: `save_api_key`, `generate_api_key` (CSRF required).
- **Admin toolbar:** when `itm_is_admin()`, index intro shows **ADMIN** (`admin.php`) and **SCRIPTS** (`scripts/scripts.php`) buttons above the UI Configuration card.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import of settings (though rare).
- Rate-limit probe: `GET scripts/api.php?rate_limit=1` — **Free** omits `api_key` when `PHPSESSID` is signed in; **paid** sends `api_key`. See `includes/itm_api_rate_limit.php` and `scripts/api.php`.

## 7. File Structure
- **index.php** — main settings dashboard.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Configuration changes should be logged.

## 10. Common Pitfalls
- **Broken Sidebar**: Incorrectly updating sidebar JSON can hide entire modules from users. [Cursor-Valid]
- **Destructive SQL**: Manual SQL imports in the settings module can overwrite entire database tables. [Cursor-Valid]
- **Tier edits**: Do not accept `tier` from POST in Settings; tier is platform-managed on `ui_configuration`. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Read UI config (never hardcode pagination)
```php
$uiConfig = itm_get_ui_configuration($conn, $companyId, $employeeId);
$perPage = itm_resolve_records_per_page($uiConfig);
```

### Free-tier API key POST rejection
```php
if ($tier === 'Free' && in_array($postAction, ['save_api_key', 'generate_api_key'], true)) {
    // reject — Free tier uses session identity only
}
```

## 12. Module Owner Notes (Optional)
The primary administrative interface for system behaviour and per-user API integration keys.
