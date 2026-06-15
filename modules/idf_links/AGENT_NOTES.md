# AGENT_NOTES.md - IDF Links

## 1. Module Purpose
Manages links and connections between different IDF locations or racks.

## 2. Key Tables
- **idf_links** — tracks inter-IDF connectivity.

## 3. Required Relationships
- **idf_links** → depends on **companies**.
- **idf_links** → depends on **idfs** (source and target).

## 4. Business Rules (Critical for Agents)
- **Bidirectional/Uni**: Tracks physical cable links between distribution frames.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 12. Module Owner Notes (Optional)
Critical for mapping the backbone of the network.
