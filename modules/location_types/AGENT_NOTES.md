# AGENT_NOTES.md - Location Types

## 1. Module Purpose
Lookup table for types of locations (e.g., "Branch", "DataCenter", "Headquarters").

## 2. Key Tables
- **location_types** — stores type names and status.

## 3. Required Relationships
- **location_types** → depends on **companies**.
- **location_types** → referenced by **it_locations**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Type name must be unique per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 12. Module Owner Notes (Optional)
Categorizes the different types of facilities managed.
