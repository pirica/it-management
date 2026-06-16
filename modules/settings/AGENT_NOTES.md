# AGENT_NOTES.md - Settings

## 1. Module Purpose
Central hub for system-wide configuration, UI customization, sidebar management, and database maintenance/backups.

## 2. Key Tables
- **ui_configuration** — stores UI element positioning, pagination, favicon, and per-user API key / rate-limit metadata.
- **user_sidebar_preferences** — stores the visibility and order of sidebar items for users.
- **equipment_types** — (partially managed here for icons/emojis).

## 3. Required Relationships
- Scoped by **companies**.
- Sidebar preferences linked to **users**.
- API keys are stored per `company_id` + `user_id` on `ui_configuration`.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **UI Persistence**: Changes to button positions or pagination must call `collectAndSetHiddenFields()` in the UI.
- **API Access card**: **Free** tier — no API key UI; copy states **signed-in session** is required for programmatic access and for `scripts/api.php?rate_limit=1` without `api_key`. **Paid** tiers — only `api_key` is editable (save or generate). `tier` is a **blocked** `<select>`; rate-limit counters are read-only. POST `save_api_key` / `generate_api_key` rejected on Free tier.
- **Database Maintenance**: Allows triggering schema verification and table repairs.
- **Backup/Restore**: Handles SQL dump generation and manual SQL imports.

## 5. UI Behavior Requirements
- **Sidebar Toggles**: Uses checkboxes with a specific `change` event listener to ensure configuration persistence.
- **Favicon/SQL Uploads**: Supports drag-and-drop file uploads for favicon and SQL backup files.
- **API key POST actions**: `save_api_key`, `generate_api_key` (CSRF required).

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
- **Broken Sidebar**: Incorrectly updating sidebar JSON can hide entire modules from users.
- **Destructive SQL**: Manual SQL imports in the settings module can overwrite entire database tables.
- **Tier edits**: Do not accept `tier` from POST in Settings; tier is platform-managed on `ui_configuration`.

## 12. Module Owner Notes (Optional)
The primary administrative interface for system behaviour and per-user API integration keys.
