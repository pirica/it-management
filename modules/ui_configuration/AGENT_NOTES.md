# AGENT_NOTES.md - UI Configuration

## 1. Module Purpose
Stores user-specific or company-wide UI layout preferences (button positions, table actions, records per page).

## 2. Key Tables
- **ui_configuration** — layout keys, API key, tier (`ENUM`), and rate-limit counters per company/user.

## 3. Required Relationships
- **ui_configuration** → **companies**, **users** (optional user override).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Fallback:** user config → company default → system hardcoded defaults (`itm_get_ui_configuration()`).
- Modules must read settings via `itm_get_ui_configuration()` — Actions columns need `itm-actions-cell` + `data-itm-actions-origin="1"`.
- **API columns:** `api_key`, `api_key_is_active`, `api_key_last_used_at`, `rate_limit_window_start`, `rate_limit_request_count`, `rate_limit_enabled`, `tier` (`Free`/`Basic`/`Pro`/`Enterprise`). **Free** tier is unlimited; paid tiers enforce hourly caps via `includes/itm_api_rate_limit.php`.

## 5. UI Behavior Requirements
- Changes affect list toolbars, pagination size, and action column placement app-wide.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; optional per-user rows within tenant.

## 12. Module Owner Notes (Optional)
Used by `js/ui-layout.js` and flattened CRUD modules for layout engine mapping.
