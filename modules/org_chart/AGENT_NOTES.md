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
- **Responsive:** chart viewport height capped on mobile; toolbar wraps; nodes scale to `min(240px, 80vw)` below 768px.

## 6. API Actions (If Applicable)
- **update_hierarchy** (POST `index.php`, `action=update_hierarchy`) — params: `employee_id`, `reports_to` (0 = top-level). Runs `itm_is_circular_reporting()` before UPDATE; returns JSON via `itm_api_json_response()` / `itm_api_mutation_requires_rows()` (HTTP 404 when zero rows affected).

## 7. File Structure
- `index.php` — chart UI, cycle detection, AJAX save handler, html2canvas export.

## 8. Multi-Tenant Rules
- All employees filtered by `company_id`; managers must be same tenant.

## 9. Audit Logging Requirements
- `employees.reports_to` updates are logged via `trg_employees_audit_*` when audit logs enabled.

## 10. Common Pitfalls
- Allowing `reports_to` loops (A→B→A or deeper cycles). [Cursor-Valid]
- Showing employees with `on_orgchart = 0`. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Cycle check before UPDATE
```php
if (itm_is_circular_reporting($employeeMap, $reportsTo, $employeeId)) {
    itm_api_json_response(['ok' => false, 'message' => 'Circular reporting line detected.'], 400);
}
$stmt = $conn->prepare('UPDATE employees SET reports_to = ? WHERE id = ? AND company_id = ?');
```

## 12. Module Owner Notes (Optional)
Primary management-structure visualisation.
