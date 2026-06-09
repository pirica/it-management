# AGENT_NOTES.md - Workstation Office

## 1. Module Purpose
Lookup table for workstation Office (e.g., specific to workstation configurations and asset management).

## 2. Key Tables
- **workstation_office** — stores office names and status.

## 3. Required Relationships
- **workstation_office** → depends on **companies**.
- Referenced by workstation asset management modules.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique within a `company_id`.

## 12. Module Owner Notes (Optional)
Categorizes workstation-specific hardware and software configurations.
