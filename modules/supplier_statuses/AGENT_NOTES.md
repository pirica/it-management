# AGENT_NOTES.md - Supplier Statuses

## 1. Module Purpose
Lookup table for the status of suppliers (e.g., "Active", "Preferred", "Blacklisted").

## 2. Key Tables
- **supplier_statuses** — stores status names and active flags.

## 3. Required Relationships
- **supplier_statuses** → depends on **companies**.
- **supplier_statuses** → referenced by **suppliers**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Status name must be unique per company.

## 12. Module Owner Notes (Optional)
Used for vendor management and procurement.
