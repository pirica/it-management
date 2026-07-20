# AGENT_NOTES.md - IDF Positions

## 1. Module Purpose
Defines rack-unit placement of devices within an IDF (`position_no`, device type, name, RJ45/SFP counts, optional equipment link, price, and layout). Positions are the parent of **idf_ports** and anchor sync to **equipment** and **switch_ports**.

## 2. Key Tables
- **idf_positions** — device slot in an IDF rack (`idf_id`, `position_no`, `device_type`, `device_name`, port counts, `equipment_id`, `price`), including `active` (tinyint DEFAULT 1, hidden field).
- **idfs** — parent frame (`idf_id`, `ON DELETE CASCADE`).
- **idf_device_type** — device type lookup (`device_type`, `ON DELETE RESTRICT`).
- **idf_ports** — child ports generated per position (`position_id`).
- **switch_ports** — mirrored when `equipment_id` links to **equipment**.
- **equipment** — optional link via string `equipment_id` (hostname/token); `equipment.idf_id` must match IDF state.

## 3. Required Relationships
- **idf_positions** → depends on **companies** (`company_id`).
- **idf_positions** → depends on **idfs** (`idf_id`).
- **idf_positions** → depends on **idf_device_type** (`device_type`).
- **idf_positions** → optional **switch_port_numbering_layout** (`switch_port_numbering_layout_id`).
- **idf_positions** → logical link to **equipment** (`equipment_id` varchar; FK map added in `index.php` when schema omits it).
- **idf_positions** → referenced by **idf_ports**; deleting a position cascades port cleanup only when handlers also clear **idf_links** and sync **switch_ports**.

## 4. Business Rules (Critical for Agents)
- **Unique rack slot:** `(company_id, idf_id, position_no)` must be unique (`idf_pos_unique`).
- **No overlap:** one device per rack unit per IDF; move/reorder uses temporary offsets (1000) for batch updates in API handlers.
- **High-density:** supports up to **250** positions per rack validation paths in `modules/idfs/`.
- **Port counts:** `rj45_count` and `sfp_count` drive port regeneration; changing counts requires `ports_regen` / sync logic, not silent DB edits alone.
- **IDF sync guardrail (mandatory):** `position_save`, `position_delete`, `position_copy`, `position_move`, and `position_reorder` must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` synchronized. Use transactions; rollback on failure.
- **Delete safety:** clean up `idf_links` and `idf_ports` (and mirrored `switch_ports`) before removing or replacing a position.
- **Price field:** token-style unlinked `equipment_id` values (`^[0-9]{4}-[0-9]{4}$`) sync price to Rack Planner sources per `AGENTS.md`.

## 5. UI Behavior Requirements
- **Standard flattened CRUD** via `index.php`.
- **Rack elevation:** primary visual UI is `modules/idfs/view.php`; this module provides direct table access.
- **FK labels:** show IDF name, device type name, equipment hostname, and layout name — not raw IDs.
- **Persisted FK dropdowns:** append saved `device_type`, `idf_id`, or `equipment_id` when company-scoped option queries omit them.
- **Search, sort, pagination,** bulk actions, export, and `import_excel_rows`.
- **Hide `company_id`** from all UI views.

## 6. API Actions (If Applicable)
- **import_excel_rows** (POST JSON on `index.php`) — bulk import into `idf_positions`.
- **Rack workflows (preferred):** `modules/idfs/api/position_save.php`, `position_delete.php`, `position_copy.php`, `position_move.php`, `position_reorder.php`, `position_get.php`, `position_remove_slot.php`.

## 7. File Structure
- **index.php** — central CRUD, equipment FK map override, import, and deletes.
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — wrappers for `$crud_action`.
- **index.html** — directory listing guard.

## 8. Multi-Tenant Rules
- All operations scoped by `company_id` from `$_SESSION['company_id']`.
- `idf_id` must belong to the same company; reject cross-tenant parent IDs.
- Hide `company_id` in UI.
- Equipment lookups must filter by `company_id` when resolving `equipment_id`.

## 9. Audit Logging Requirements
`db/` registers audit triggers on `idf_positions`:

| Trigger | Actions | Payload highlights |
|---------|---------|-------------------|
| `trg_idf_positions_audit_insert` | INSERT | `idf_id`, `position_no`, `device_type`, `device_name`, `equipment_id`, port counts, `price`, layout, `notes` |
| `trg_idf_positions_audit_update` | UPDATE | old/new JSON for position fields |
| `trg_idf_positions_audit_delete` | DELETE | deleted position JSON |

Triggers always write to `audit_logs` with `table_name = 'idf_positions'` on DML (not gated by `enable_audit_logs`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Deleting positions with links:** remove `idf_links` on child ports first; API `position_delete.php` encodes the correct order — do not bypass with bare SQL. [Cursor-Valid]
- **IDF sync:** after position create/copy/move/delete changes, run `php scripts/idfs_sync_human_test.php`; any `[FAIL]` means the task is incomplete. [Cursor-Valid]
- **Port regeneration:** changing `rj45_count` / `sfp_count` in flat CRUD without `ports_regen` leaves `idf_ports` and `switch_ports` counts wrong. [Cursor-Valid]
- **Equipment link drift:** stale `equipment.idf_id` or missing `switch_ports.idf_id` breaks port tiles in `modules/equipment/`. [Cursor-Valid]
- **RESTRICT on device type:** cannot delete `idf_device_type` rows still referenced by `device_type`. [Cursor-Valid]
- **Module consistency:** propagate renderer and FK fallback fixes across `index.php`, `view.php`, `edit.php`, `create.php`, and `list_all.php`. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (positions in an IDF)
```php
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, position_no, device_type, device_name, equipment_id, rj45_count, sfp_count
     FROM idf_positions
     WHERE company_id = ? AND idf_id = ?
     ORDER BY position_no'
);
mysqli_stmt_bind_param($stmt, 'ii', $company_id, $idf_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
```

### Safe INSERT (new rack position)
```php
$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO idf_positions (
        company_id, idf_id, position_no, device_type, device_name,
        rj45_count, sfp_count, equipment_id, notes
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param(
    $stmt,
    'iiiissiis',
    $company_id,
    $idf_id,
    $position_no,
    $device_type,
    $device_name,
    $rj45_count,
    $sfp_count,
    $equipment_id,
    $notes
);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

## 12. Module Owner Notes (Optional)
Physical layout planning for networking racks. Interactive drag-and-drop and copy/move semantics live in `modules/idfs/view.php` and `api/`; this flattened module is for administrative CRUD and imports. Treat any position change as a potential sync event across ports, links, and equipment.
