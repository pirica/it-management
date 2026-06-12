# AGENT_NOTES.md - Inventory Categories

## 1. Module Purpose
Lookup table for inventory item categories (e.g., "Cables", "Peripherals", "Supplies").

## 2. Key Tables
- **inventory_categories** — stores category names and status.

## 3. Required Relationships
- **inventory_categories** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Category name must be unique per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 12. Module Owner Notes (Optional)
Used to organize consumables and spare parts.
