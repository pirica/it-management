# AGENT_NOTES.md - UI Configuration

## 1. Module Purpose
Stores and manages user-specific or company-wide UI layout preferences.

## 2. Key Tables
- **ui_configuration** — stores positions of buttons and table elements.

## 3. Required Relationships
- **ui_configuration** → depends on **companies**.
- **ui_configuration** → depends on **users** (for user-specific overrides).

## 4. Business Rules (Critical for Agents)
- **Fallback Logic**: If no user-specific config exists, it should fall back to company defaults or system hardcoded defaults.

## 5. UI Behavior Requirements
- **Real-time Updates**: Settings should be applied immediately to the UI elements.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 12. Module Owner Notes (Optional)
Used by the system to customize the user experience.
