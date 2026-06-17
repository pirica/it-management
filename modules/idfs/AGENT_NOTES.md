# AGENT_NOTES.md - IDFs

## 1. Module Purpose
Manages Intermediate Distribution Frames (IDFs): physical network distribution points, rack association, location metadata, and the integrated rack dashboard (positions, ports, links, and equipment). This is the central entry point for IDF physical infrastructure in the product.

## 2. Key Tables
- **idfs** — main IDF header (name, code, location, rack, active flag).
- **idf_positions** — devices placed in rack units within an IDF (managed heavily from `view.php` and `api/`).
- **idf_ports** — per-position port rows mirrored to **switch_ports** when equipment is linked.
- **idf_links** — cable links between IDF ports (and optional equipment-side metadata).
- **idf_device_type** — lookup for position device types (switch, patch panel, PDU, etc.).
- **switch_ports** — authoritative numbered port rows for linked equipment (synced from IDF workflows).
- **equipment** — linked assets; `equipment.idf_id` must stay aligned with IDF link state.

## 3. Required Relationships
- **idfs** → depends on **companies** (`company_id`).
- **idfs** → depends on **it_locations** (`location_id`, optional but expected).
- **idfs** → depends on **racks** (`rack_id`, optional).
- **idfs** → referenced by **idf_positions** (`idf_id`, `ON DELETE CASCADE`).
- **idfs** → referenced by **equipment** (`idf_id` when a device is placed in the frame).
- **idf_positions** → owns **idf_ports**; ports sync to **switch_ports** for equipment-backed positions.
- **idf_links** → connects **idf_ports** (`port_id_a`, `port_id_b`).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see `AGENTS.md` Protection Zone).
- **IDF sync guardrail (mandatory):** all Create, Edit, Update, Delete, Copy, and Move workflows must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` fully synchronized. Use transaction boundaries; rollback on any failure. Partial cross-table updates are forbidden.
- **Unknown reset rule:** unlink/delete flows must reset synchronised ports to tenant `Unknown` status with Gray (`#808080`) where applicable.
- **Unique name:** `idfs.name` must be unique per `company_id` (`uq_idfs_company_scope`).
- **High-density racks:** position validation supports up to **250** rack units; batch move/reorder uses temporary offsets (1000) to avoid unique-key collisions.
- **Delete safety:** clean up dependent `idf_links` and `idf_ports` before deleting or replacing `idf_positions`; keep `equipment.idf_id` and `switch_ports.idf_id` aligned with active IDF link state.
- **Price sync:** when Rack Planner or position save updates priced unlinked tokens, persist price changes to `idf_positions.price` for matching `equipment_id` token patterns.

## 5. UI Behavior Requirements
- **List (`index.php`):** standard search, sort, pagination, bulk delete when row count ≥ `records_per_page`, Excel/PDF export, and `import_excel_rows` JSON import via `data-itm-db-import-endpoint`.
- **Dashboard (`view.php`):** rack elevation, port visualiser, link management, and inline AJAX saves — not a flat CRUD table.
- **Device screen (`device.php`):** equipment-centric port management within an IDF context.
- **FK labels:** list and detail views must show location and rack names, not raw `location_id` / `rack_id`.
- **Actions column:** use `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on header and body cells.
- **Hide `company_id`** from all UI views.

## 6. API Actions (If Applicable)
- **import_excel_rows** (POST JSON on `index.php`) — bulk import into `idfs` via `itm_handle_json_table_import()`.
- **refresh_select_options** (GET on `index.php`) — returns rack or location dropdown options for the active company.
- **`modules/idfs/api/`** — async rack workflows (see that folder's `AGENT_NOTES.md`):
  - `position_save`, `position_delete`, `position_copy`, `position_move`, `position_reorder`, `position_get`, `position_remove_slot`
  - `link_create`, `link_delete`, `port_update`, `ports_sync`, `ports_regen`
  - `switch_port_row`, `switch_ports_by_equipment`, `cable_color_add`, `switch_status_add`

## 7. File Structure
- **index.php** — IDF list, create handler, Excel import, select refresh JSON.
- **create.php**, **edit.php**, **delete.php**, **view.php**, **list_all.php** — CRUD and detail routes.
- **device.php** — device-level management inside an IDF.
- **port_visualizer_helper.php** — port grid rendering and colour/status resolution.
- **idf_ports_sync.php** — shared sync helpers between `idf_ports` and `switch_ports`.
- **idf_positions_schema.php** — position schema helpers for the rack UI.
- **api/** — AJAX endpoints for position/port/link mutations (one action per file).
- **test_visualizer_v2.php** — development/visualiser test page (not production CRUD).

## 8. Multi-Tenant Rules
- All queries and inserts must filter or set `company_id` from `$_SESSION['company_id']`.
- IDFs cannot be moved between companies; child rows (`idf_positions`, `idf_ports`, `idf_links`) inherit tenant scope.
- Hide `company_id` from list, view, and edit UIs.
- FK option queries must scope by `company_id` first; append persisted FK values when company-scoped lists omit legacy rows.

## 9. Audit Logging Requirements
Database triggers write to `audit_logs` when `enable_audit_logs` is on (session variables `@app_user_id`, `@app_company_id` set in `config/config.php`). Do not overwrite those session variables in module code.

| Trigger | Table | Actions |
|---------|-------|---------|
| `trg_idfs_audit_insert` | `idfs` | INSERT — logs `id`, `company_id`, `location_id`, `name`, `idf_code`, `notes`, `created_at` |
| `trg_idfs_audit_update` | `idfs` | UPDATE — old/new JSON for the same columns |
| `trg_idfs_audit_delete` | `idfs` | DELETE — old row JSON |

Related tables (`idf_positions`, `idf_ports`, `idf_links`, `idf_device_type`) have their own triggers; rack API mutations that touch those tables are audited via the child table triggers. After schema changes, re-run `php scripts/check_audit_logs_coverage.php`.

## 10. Common Pitfalls
- **Do not edit Protection Zone modules** without an explicit user request — includes this folder, sibling IDF CRUD modules, and `modules/equipment/` switch-port tiles.
- **IDF sync drift:** updating only `idf_ports` or only `switch_ports` leaves the rack UI and equipment module inconsistent. Always follow the sync helpers in `idf_ports_sync.php` and `api/ports_sync.php`.
- **Link delete/create parity:** `link_create`, `link_delete`, and `port_update` must keep status, colour, label, and notes aligned across linked `idf_ports` and mirrored `switch_ports` rows.
- **Position delete ordering:** remove or update `idf_links` referencing ports on the position before deleting the position row.
- **Equipment ID alignment:** stale `equipment.idf_id` or `switch_ports.idf_id` breaks port visualiser fallbacks in `view.php`.
- **Regression gate:** after any IDF workflow change, run `php scripts/idfs_sync_human_test.php` (hard fail if any `[FAIL]`). On Windows Laragon use the full PHP 7.4.33 binary path documented in `AGENTS.md`.
- **Configuration complexity:** creating or deleting an IDF cascades to positions; ensure related ports, links, and equipment references are handled in one transaction.

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant-scoped IDF by id)
```php
$stmt = mysqli_prepare($conn, 'SELECT id, name, location_id, rack_id FROM idfs WHERE company_id = ? AND id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $company_id, $idf_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
```

### Safe INSERT (create IDF header)
```php
$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO idfs (company_id, location_id, rack_id, name, idf_code, notes, active)
     VALUES (?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'iiisssi', $company_id, $location_id, $rack_id, $name, $idf_code, $notes, $active);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

## 12. Module Owner Notes (Optional)
Central module for network physical infrastructure. Rack behaviour is split between this folder (dashboard + API) and flattened CRUD modules (`idf_positions`, `idf_ports`, `idf_links`, `idf_device_type`) for direct table maintenance. Prefer `modules/idfs/api/` and shared sync helpers for any change that touches ports or links; avoid one-off updates in a single table.
