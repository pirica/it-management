# AGENT_NOTES.md - UI Configuration

## 1. Module Purpose
CRUD UI for per-user and company-default layout preferences: button positions, table actions placement, `records_per_page`, favicon, sidebar overrides, and API integration tier metadata.

## 2. Key Tables
- **ui_configuration** — `new_button_position`, `table_actions_position`, `records_per_page`, `module_icon_overrides` JSON, `api_key`, `tier`, rate-limit counters, `enable_audit_logs`, `enable_all_error_reporting`.

## 3. Required Relationships
- **ui_configuration** → **companies**, **users** (row may be company default or per-user override).
- Consumed app-wide via `itm_get_ui_configuration()` in `config/config.php` and flattened CRUD modules.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see `AGENTS.md` §3).
- **Fallback chain:** user row → company default → hardcoded defaults in `itm_get_ui_configuration()`.
- Modules must read settings via helper — never hardcode pagination or toolbar positions.
- **API columns:** `tier` ENUM (`Free`/`Basic`/`Pro`/`Enterprise`); Free = session identity, no API key; paid tiers require `api_key` for keyless HTTP. Do not accept `tier` from POST in edit forms here or in Settings.
- **Actions column contract:** list tables need `itm-actions-cell` + `data-itm-actions-origin="1"` for `js/ui-layout.js`.

## 5. UI Behavior Requirements
- Standard flattened CRUD for configuration rows (admin contexts).
- Changes affect list toolbars, pagination size, and action column placement app-wide after save.
- Rate-limit counters and `api_key_last_used_at` are read-only in UI.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — rare bulk import of configuration rows.
- **Rate-limit probe** (external): `GET scripts/api.php?rate_limit=1` reads tier from active user's `ui_configuration` row — not handled in this module folder.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php` — flattened CRUD (Protection Zone).

## 8. Multi-Tenant Rules
- Scoped by `company_id`; optional per-user rows (`user_id`) within tenant.
- Hide `company_id` from standard list/detail where applicable.

## 9. Audit Logging Requirements
- `trg_ui_configuration_audit_insert|update|delete` in `database.sql`.
- API key values must not be echoed in audit payloads to non-owner users.

## 10. Common Pitfalls
- Editing `records_per_page` without testing bulk-toolbar gate (`$totalRows >= $perPage`) on high-traffic modules.
- Storing invalid JSON in `module_icon_overrides` — breaks SideMenu rendering in Settings.
- Accepting `tier` from POST — allows quota bypass.
- Duplicating layout keys instead of using `itm_get_ui_configuration()` — causes inconsistent UI between modules.

## 11. Examples of Safe Code Patterns

### Resolve effective UI config in a module
```php
$uiConfig = itm_get_ui_configuration($conn, $companyId, $loggedUserId);
$perPage = itm_resolve_records_per_page($uiConfig);
$showBulkActions = ($totalRows >= $perPage);
```

## 12. Module Owner Notes (Optional)
Used by `js/ui-layout.js` and flattened CRUD modules for layout engine mapping. Settings module (`modules/settings/`) is the primary user-facing editor for many of these fields — keep both docs aligned when changing API tier behaviour.
