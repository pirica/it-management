# AGENT_NOTES.md - Switch Ports

## 1. Module Purpose
Manages individual ports on a switch device, tracking connectivity, VLANs, and status.

## 2. Key Tables
- **switch_ports** — main port data, including `active` (tinyint DEFAULT 1, hidden field).

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
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.
- **Grid Layout**: Often rendered in a grid mimicking the physical switch layout (via equipment module tiles).
- Wrapper entry files set `$crud_action` before `require index.php` — index must not overwrite wrapper value.
- Create/edit forms use `$uiColumns` (business fields only) with `itm_crud_render_form_hidden_audit_inputs()` for audit stamps; list/view keep `$visibleFieldColumns`.
- Standard flattened list in `list_all.php` when not embedded in equipment view.
- **List search:** `index.php` / `list_all.php` search matches FK label tables via `itm_crud_fk_label_search_conditions()` (status, VLAN, equipment hostname, etc.), not only raw numeric IDs.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import on `index.php`.
- **Shared Switch Port Manager endpoints** (used from `modules/equipment/index.php` tiles, not module-local PHP):
  - **`includes/get_ports.php`** — fetch/seed ports and return lookup maps for the port grid UI.
  - **`includes/update_port.php`** — persist port label/status/color/VLAN/IDF fields; keeps `idf_ports` aligned when To IDF sync runs.
  - Both require session `company_id`, CSRF, POST only; JSON via `itm_api_json_response()` (`JSON_UNESCAPED_UNICODE`). Prepared reads use `itm_mysqli_stmt_fetch_assoc()` / `itm_mysqli_stmt_fetch_all_assoc()` (mysqlnd fallback). See `scripts/api.php` → Switch Port Manager API.
- **Switch Port Manager transactions and tenant scoping:**
  - Port updates that change management or To IDF fields **must** keep `switch_ports`, `idf_ports`, and related IDF tables transactionally consistent. **`includes/update_port.php`** implements this with `mysqli_begin_transaction()` when `management_id` exists; rollback on IDF sync failure, empty-position 422, or zero-row switch_ports update (see `AGENTS.md` → IDF synchronization guardrail and `scripts/SCRIPTS.md` → Switch Port Manager AJAX).
  - Derive `company_id` from the authenticated session only; do **not** read or trust `company_id` from the request body or query string when implementing or modifying `includes/get_ports.php` or `includes/update_port.php`.
  - All SQL must use prepared statements; JSON responses must go through `itm_api_json_response()` to avoid leaking raw DB errors to clients.
  - After any change to switch-port or IDF sync logic, re-run `php scripts/idfs_sync_human_test.php` and do not ship while any `[FAIL]` results remain.

## 7. File Structure
- `index.php` — list/grid and CRUD routing (may absorb wrapper actions).
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — optional wrappers.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; ports must match parent switch/equipment tenant.

## 9. Audit Logging Requirements
- `trg_switch_ports_audit_insert|update|delete` in `database.sql`.
- IDF-linked port changes may also appear in **idf_ports** audit triggers — keep both tables in sync.

## 10. Common Pitfalls
- **Mismatched IDs**: Ensure the port belongs to the correct switch and company. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM switch_ports WHERE switch_id = ? AND company_id = ?");
$stmt->bind_param("ii", $switchId, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The most granular part of the network inventory.
