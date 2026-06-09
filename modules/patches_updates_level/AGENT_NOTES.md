# AGENT_NOTES.md - Patches & Updates Level

## 1. Module Purpose
Lookup table for severity or priority levels of patches (e.g., "Critical", "Recommended").

## 2. Key Tables
- **patches_updates_level** — stores level names.

## 3. Required Relationships
- **patches_updates_level** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Level name must be unique per company.

## 12. Module Owner Notes (Optional)
Used to prioritize patching tasks.
