# AGENT_NOTES.md - IDF Links

## 1. Module Purpose
Stores physical cable links between IDF ports (`port_id_a` and `port_id_b`), including optional equipment-side metadata (hostname, VLAN, fibre patch, cable colour, labels). Links drive connectivity shown in the IDF rack dashboard and must stay aligned with port status on both ends.

## 2. Key Tables
- **idf_links** — link header and equipment/cable metadata, including `active` (tinyint DEFAULT 1, hidden field).
- **idf_ports** — endpoint ports referenced by `port_id_a` / `port_id_b` (not owned by this module but always updated in sync).
- **switch_ports** — mirrored rows for equipment-backed ports when links are created or removed via `modules/idfs/api/`.

## 3. Required Relationships
- **idf_links** → depends on **companies** (`company_id`).
- **idf_links** → depends on **idf_ports** (`port_id_a`, `port_id_b`; unique pair per company: `uniq_pair`).
- **idf_links** → optional references to **equipment** (string `equipment_id`), **vlans**, **rj45_speed**, fibre patch/rack tables, **idfs** (`equipment_to_idf_id`), **racks**, **it_locations**, **switch_status**, **cable_colors**.
- **idf_links** → created/updated/deleted in tandem with **switch_ports** and **idf_ports** status/colour/label fields from `modules/idfs/api/link_create.php` and `link_delete.php`.

## 4. Business Rules (Critical for Agents)
- **Unique port pair:** `(company_id, port_id_a, port_id_b)` must be unique; duplicate links return a constraint error.
- **Bidirectional semantics:** a link connects two IDF ports; both port rows and any mirrored `switch_ports` must reflect connected state consistently.
- **IDF sync guardrail (mandatory):** `link_create`, `link_delete`, and related `port_update` flows must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` synchronized. Use transactions; rollback on failure.
- **Unknown reset rule:** when a link is removed, reset affected ports to tenant `Unknown` status and Gray (`#808080`) defaults where the IDF API contract applies.
- **Delete ordering:** remove links before deleting positions or ports they reference.

## 5. UI Behavior Requirements
- **Standard flattened CRUD** via `index.php` with dynamic schema detection.
- **FK dropdowns:** `port_id_a` and `port_id_b` resolve to `idf_ports` labels; show port/position context, not bare IDs.
- **Extended FK map:** `index.php` adds logical FK metadata for equipment and cable fields beyond strict `information_schema` entries.
- **Search, sort, pagination,** bulk actions, and export per global UI configuration.
- **Hide `company_id`** from all UI views.

## 6. API Actions (If Applicable)
- **import_excel_rows** (POST JSON on `index.php`) — bulk import into `idf_links`.
- **Primary link mutations** are implemented under `modules/idfs/api/link_create.php` and `link_delete.php` (preferred for rack UI parity). Flat CRUD in this module is for direct table maintenance only.

## 7. File Structure
- **index.php** — central CRUD, import, FK rendering, and delete handlers.
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — wrappers setting `$crud_action`.
- **index.html** — directory listing guard.

## 8. Multi-Tenant Rules
- Every query must scope by `company_id` from the active session.
- Port endpoints must belong to the same company; validating `idf_ports.company_id` before insert prevents cross-tenant links.
- Hide `company_id` in all user-facing screens.

## 9. Audit Logging Requirements
Triggers in `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` log all INSERT, UPDATE, and DELETE operations on `idf_links` to `audit_logs` when audit logging is enabled.

| Trigger | Actions | Payload highlights |
|---------|---------|-------------------|
| `trg_idf_links_audit_insert` | INSERT | `port_id_a`, `port_id_b`, equipment fields, cable colour/label, `notes` |
| `trg_idf_links_audit_update` | UPDATE | full old/new JSON for link and equipment metadata columns |
| `trg_idf_links_audit_delete` | DELETE | deleted row JSON |

Actor context uses `@app_employee_id` and related session variables from `config.php`; do not overwrite them in handlers.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Updating `idf_links` alone:** rack and equipment UIs read `idf_ports` / `switch_ports` status and colour — link CRUD here must mirror the same fields or use the shared API helpers. [Cursor-Valid]
- **IDF sync regression:** after any link workflow change, run `php scripts/idfs_sync_human_test.php`; any `[FAIL]` blocks completion. [Cursor-Valid]
- **Orphan links:** deleting `idf_ports` without clearing links leaves broken FK references or unique-key conflicts on recreate. [Cursor-Valid]
- **Port pair direction:** `port_id_a` and `port_id_b` are ordered in the unique key; swapping ends may duplicate if both orientations are inserted. [Cursor-Valid]
- **Raw FK IDs in list/view:** resolve `port_id_a` / `port_id_b` to position/device labels; resolve equipment and VLAN FKs to names. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT (link for active company)
```php
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, port_id_a, port_id_b, cable_label, notes
     FROM idf_links
     WHERE company_id = ? AND id = ?'
);
mysqli_stmt_bind_param($stmt, 'ii', $company_id, $link_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
```

### Safe INSERT (link between two ports)
```php
$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO idf_links (company_id, port_id_a, port_id_b, cable_label, notes)
     VALUES (?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'iiiss', $company_id, $port_id_a, $port_id_b, $cable_label, $notes);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

## 12. Module Owner Notes (Optional)
Critical for mapping backbone connectivity between distribution frames. Interactive link creation in the rack UI should go through `modules/idfs/api/` so port mirrors stay consistent; use this flattened module for imports, audits, and administrative edits with extra care for sync side effects.
