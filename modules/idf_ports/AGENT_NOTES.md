# AGENT_NOTES.md - IDF Ports

## 1. Module Purpose
Manages individual port rows on IDF rack positions (RJ45 and SFP), including status, VLAN, speed, PoE, colour, labels, and cross-IDF routing metadata. Rows pair with **switch_ports** when the parent position is linked to **equipment**.

## 2. Key Tables
- **idf_ports** — port configuration per `position_id` (`port_no`, `port_type`, status, VLAN, speed, colour, notes), including `active` (tinyint DEFAULT 1, hidden field).
- **idf_positions** — parent device slot (`position_id` FK).
- **switch_ports** — mirrored numbered ports for equipment-backed positions (authoritative for many RJ45/SFP layouts).
- **idf_links** — references ports via `port_id_a` / `port_id_b`.

## 3. Required Relationships
- **idf_ports** → depends on **companies** (`company_id`).
- **idf_ports** → depends on **idf_positions** (`position_id`; unique per company/position/port: `pos_port_unique`).
- **idf_ports** → depends on **switch_status** (`status_id`), **vlans** (`vlan_id`), **rj45_speed**, **poe**, fibre patch/rack tables, **switch_port_numbering_layout**, and optional **idfs** / **racks** / **it_locations** for `to_*` routing fields.
- **idf_ports** ↔ **switch_ports** — kept in sync by `modules/idfs/idf_ports_sync.php` and `modules/idfs/api/port_update.php`, `ports_sync.php`, `ports_regen.php`.
- **idf_links** — must be updated or removed before port delete when links reference the port.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see `AGENTS.md` Protection Zone).
- **Unique port slot:** `(company_id, position_id, port_no, port_type)` must be unique.
- **Port numbering:** sequential `port_no` within a position; SFP numbering often follows live `switch_ports` when equipment is linked.
- **IDF sync guardrail (mandatory):** any create/update/delete affecting ports must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` aligned. Use transactions; rollback on failure.
- **Status/colour parity:** `link_create`, `link_delete`, and `port_update` must preserve status, colour, label, and notes across linked `idf_ports` and `switch_ports`.
- **Unknown reset:** unlink flows reset ports to tenant `Unknown` + Gray (`#808080`) where applicable.

## 5. UI Behavior Requirements
- **Standard flattened CRUD** via `index.php`.
- **FK labels:** render status names, VLAN names, speed labels, and position/device context — never raw `status_id` or `position_id` when label rows exist.
- **Position dropdown:** `position_id` options should show device name and rack position; `index.php` includes special handling for position FK display.
- **Port visualiser:** grid rendering is primarily in `modules/idfs/view.php` and `port_visualizer_helper.php`; list CRUD here is supplementary.
- **Search, sort, pagination,** bulk delete, export, and `import_excel_rows` per global standards.
- **Hide `company_id`** from all UI views.

## 6. API Actions (If Applicable)
- **import_excel_rows** (POST JSON on `index.php`) — bulk import into `idf_ports`.
- **Interactive port updates:** `modules/idfs/api/port_update.php`, `ports_sync.php`, `ports_regen.php` — preferred for rack UI because they mirror `switch_ports`.

## 7. File Structure
- **index.php** — central CRUD, import, FK maps, and delete logic.
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — action wrappers.
- **index.html** — directory listing guard.

## 8. Multi-Tenant Rules
- Scope all queries with `company_id` from the session.
- `position_id` must reference an `idf_positions` row for the same `company_id`.
- Hide `company_id` in list, view, and edit screens.
- Tenant-safe FK fallback: company-scoped lookup first, then id-only fallback only for legacy shared reference rows.

## 9. Audit Logging Requirements
`database.sql` defines AFTER INSERT/UPDATE/DELETE triggers that write JSON payloads to `audit_logs`:

| Trigger | Actions | Payload highlights |
|---------|---------|-------------------|
| `trg_idf_ports_audit_insert` | INSERT | `position_id`, `port_no`, `port_type`, `label`, `status_id`, VLAN/speed/PoE, colours, `notes` |
| `trg_idf_ports_audit_update` | UPDATE | old/new JSON for the same port fields |
| `trg_idf_ports_audit_delete` | DELETE | deleted port row JSON |

Triggers always write to `audit_logs` on DML (not gated by `enable_audit_logs`); actor fields come from MySQL session variables set in `config.php`.

## 10. Common Pitfalls
- **Protection Zone:** do not change port sync contracts without explicit request.
- **Editing `idf_ports` only:** leaves `switch_ports` and the equipment switch-port manager out of date — always use sync helpers or API endpoints.
- **IDF sync test:** run `php scripts/idfs_sync_human_test.php` after any port workflow change; treat `[FAIL]` as a hard stop.
- **Deleting linked ports:** remove `idf_links` referencing the port first; otherwise deletes fail or leave orphan link rows.
- **Partial fibre/RJ45 sets:** `idf_ports_sync.php` treats `switch_ports` as authoritative for SFP numbering — do not synthesise duplicate fibre rows in `idf_ports`.
- **List/search bug:** define `$displayFieldColumns = $uiColumns` before search loops that reference `$displayFieldColumns`.

## 11. Examples of Safe Code Patterns

### Safe SELECT (ports for a position)
```php
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, port_no, port_type, label, status_id, vlan_id, hex_color
     FROM idf_ports
     WHERE company_id = ? AND position_id = ?
     ORDER BY port_type, port_no'
);
mysqli_stmt_bind_param($stmt, 'ii', $company_id, $position_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
```

### Safe UPDATE (port label and status)
```php
$stmt = mysqli_prepare(
    $conn,
    'UPDATE idf_ports
     SET label = ?, status_id = ?, hex_color = ?
     WHERE company_id = ? AND id = ?'
);
mysqli_stmt_bind_param($stmt, 'sisii', $label, $status_id, $hex_color, $company_id, $port_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

## 12. Module Owner Notes (Optional)
Granular port table for IDF management. Day-to-day rack edits should flow through `modules/idfs/view.php` and `api/` so `switch_ports` and link state stay aligned. Use this CRUD module for imports, support fixes, and audit visibility with sync awareness.
