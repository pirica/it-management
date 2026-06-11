# AGENT_NOTES.md - Settings

## 1. Module Purpose
Central hub for system-wide configuration, UI customization, sidebar management, and database maintenance/backups.

## 2. Key Tables
- **ui_configuration** — stores UI element positioning and pagination settings.
- **user_sidebar_preferences** — stores the visibility and order of sidebar items for users.
- **equipment_types** — (partially managed here for icons/emojis).

## 3. Required Relationships
- Scoped by **companies**.
- Sidebar preferences linked to **users**.

## 4. Business Rules (Critical for Agents)
- **UI Persistence**: Changes to button positions or pagination must call `collectAndSetHiddenFields()` in the UI.
- **Database Maintenance**: Allows triggering schema verification and table repairs.
- **Backup/Restore**: Handles SQL dump generation and manual SQL imports.

## 5. UI Behavior Requirements
- **Sidebar Toggles**: Uses checkboxes with a specific `change` event listener to ensure configuration persistence.
- **Favicon/SQL Uploads**: Supports drag-and-drop file uploads for favicon and SQL backup files.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import of settings (though rare).

## 7. File Structure
- **index.php** — main settings dashboard.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Configuration changes should be logged.

## 10. Common Pitfalls
- **Broken Sidebar**: Incorrectly updating sidebar JSON can hide entire modules from users.
- **Destructive SQL**: Manual SQL imports in the settings module can overwrite entire database tables.

## 12. Module Owner Notes (Optional)
The primary administrative interface for system behavior.
