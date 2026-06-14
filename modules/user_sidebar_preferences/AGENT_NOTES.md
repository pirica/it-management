# AGENT_NOTES.md - User Sidebar Preferences

## 1. Module Purpose
Stores the custom order and visibility of sidebar modules for each user.

## 2. Key Tables
- **user_sidebar_preferences** — stores JSON or individual toggles for sidebar items.

## 3. Required Relationships
- **user_sidebar_preferences** → depends on **companies**.
- **user_sidebar_preferences** → depends on **users**.

## 4. Business Rules (Critical for Agents)
- **Immediate Effect**: Sidebar must reflect these preferences on every page load.

## 12. Module Owner Notes (Optional)
Allows users to personalize their navigation experience.
