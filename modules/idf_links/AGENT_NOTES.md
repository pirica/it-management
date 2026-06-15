# AGENT_NOTES.md - IDF Links

## 1. Module Purpose
Manages links and connections between different IDF locations or racks.

## 2. Key Tables
- **idf_links** — tracks inter-IDF connectivity.

## 3. Required Relationships
- **idf_links** → depends on **companies**.
- **idf_links** → depends on **idfs** (source and target).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **Bidirectional/Uni**: Tracks physical cable links between distribution frames.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.


## 12. Module Owner Notes (Optional)
Critical for mapping the backbone of the network.
