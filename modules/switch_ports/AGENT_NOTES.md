# AGENT_NOTES.md - Switch Ports

## 1. Module Purpose
Manages individual ports on a switch device, tracking connectivity, VLANs, and status.

## 2. Key Tables
- **switch_ports** — main port data.

## 3. Required Relationships
- **switch_ports** → depends on **companies**.
- **switch_ports** → depends on **equipment** (the Switch).
- **switch_ports** → depends on **switch_port_types**.
- **switch_ports** → depends on **switch_status**.
- **switch_ports** → links to **vlans**.

## 4. Business Rules (Critical for Agents)
- **IDF synchronization:** port create/update/delete must stay aligned with **idf_ports** and linked **idf_links** / **equipment** — same transaction rules as `modules/idfs/` (see AGENTS.md IDF guardrail).
- **Wrapper routing:** `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` may set `$crud_action` before requiring `index.php` — **do not overwrite** wrapper-provided `$crud_action` in `index.php`.
- **Port Sorting:** UI sorts RJ45 before other types via CASE in ORDER BY.
- **Foreign Key Mapping:** AJAX handlers map empty/0 IDs to NULL.

## 5. UI Behavior Requirements
- **Grid Layout**: Often rendered in a grid mimicking the physical switch layout.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- `index.php` — list/grid and CRUD routing (may absorb wrapper actions).
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — optional wrappers.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; ports must match parent switch/equipment tenant.

## 10. Common Pitfalls
- **Mismatched IDs**: Ensure the port belongs to the correct switch and company.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM switch_ports WHERE switch_id = ? AND company_id = ?");
$stmt->bind_param("ii", $switchId, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The most granular part of the network inventory.
