# AGENT_NOTES.md - Org Chart

## 1. Module Purpose
Interactive org chart from `employees.reports_to` reporting lines with drag-and-drop hierarchy updates.

## 2. Key Tables
- **employees** — `reports_to` self-FK, `on_orgchart`, `department_id`, `employee_position_id`.
- **departments**, **employee_positions** — labels on nodes.

## 3. Required Relationships
- **employees** → **companies**; self-reference `reports_to` → **employees.id**.

## 4. Business Rules (Critical for Agents)
- **Visibility:** only active employees with `on_orgchart = 1`.
- **Cycle detection (mandatory):** hierarchy updates (drag-and-drop) must recursively detect cycles — no employee may report into their subtree.
- **Persistence:** drag-and-drop saves immediately via AJAX to `employees.reports_to`.
- **Layout:** dynamic tree positioning algorithm positions nodes from reporting lines.

## 5. UI Behavior Requirements
- Visual tree diagram; optional image export.

## 6. API Actions (If Applicable)
- Hierarchy update AJAX — validates cycles before UPDATE.

## 7. File Structure
- `index.php` — chart UI + AJAX save handler.

## 8. Multi-Tenant Rules
- All employees filtered by `company_id`; managers must be same tenant.

## 10. Common Pitfalls
- Allowing `reports_to` loops (A→B→A or deeper cycles).
- Showing employees with `on_orgchart = 0`.

## 12. Module Owner Notes (Optional)
Primary management-structure visualisation.
