# AGENT_NOTES.md - Workstation Os Versions

## 1. Module Purpose
Lookup table for workstation Os Versions (e.g., specific to workstation configurations and asset management).

## 2. Key Tables
- **workstation_os_versions** — stores os_versions names and status.

## 3. Required Relationships
- **workstation_os_versions** → depends on **companies**.
- Referenced by workstation asset management modules.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique within a `company_id`.

## 12. Module Owner Notes (Optional)
Categorizes workstation-specific hardware and software configurations.
