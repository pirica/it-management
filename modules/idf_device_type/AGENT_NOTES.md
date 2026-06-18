# AGENT_NOTES.md - IDF Device Type

## 1. Module Purpose
Maintains the company-scoped lookup of device types used on IDF rack positions (for example switch, patch panel, UPS, server). Each row supplies a display name and optional emoji used in rack elevation UI.

## 2. Key Tables
- **idf_device_type** — device type name, emoji, and `active` flag per company.

## 3. Required Relationships
- **idf_device_type** → depends on **companies** (`company_id`, `ON DELETE CASCADE`).
- **idf_device_type** → referenced by **idf_positions** (`device_type`, `ON DELETE RESTRICT` — cannot delete a type still assigned to a position).
- **idf_device_type** → indirectly affects **idf_ports** and **switch_ports** when positions of that type are created or regenerated.

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see `AGENTS.md` Protection Zone).
- **Unique name:** `idfdevicetype_name` must be unique per `company_id` (`idf_device_type_unique`).
- **RESTRICT delete:** deleting a type that is referenced by `idf_positions.device_type` fails at the database layer; detach or reassign positions first.
- **IDF sync guardrail:** changing types or counts on positions (elsewhere) must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` synchronized — this module alone does not perform port sync, but type changes drive downstream rack behaviour.
- **Active flag:** inactive types should not be offered for new positions; preserve persisted selections on edit forms when a saved type is missing from scoped dropdowns.

## 5. UI Behavior Requirements
- **Standard flattened CRUD** via `index.php` (list, create, edit, view, delete, `list_all` wrappers).
- **Search, sort, pagination,** and export tools per `ui_configuration`.
- **Bulk delete** when `$totalRows >= $perPage`; shared `bulk-delete-selection.js` contract.
- **`active` field:** checkbox pattern with ✅/❌ in forms; badge display (Active/Inactive) in list and view — no emoji in list badges.
- **Hide `company_id`** from all UI views.
- **FK rendering:** show human-readable type names in related modules, not raw `device_type` IDs.

## 6. API Actions (If Applicable)
- **import_excel_rows** (POST JSON on `index.php`) — bulk import rows into `idf_device_type` with CSRF and company scoping.

## 7. File Structure
- **index.php** — central CRUD implementation (list, forms, delete handlers, import).
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — thin wrappers setting `$crud_action` before requiring `index.php`.
- **index.html** — directory listing guard.

## 8. Multi-Tenant Rules
- All SELECT, INSERT, UPDATE, and DELETE statements must include `company_id = ?` (or equivalent) for the active session company.
- Hide `company_id` from list, view, and edit screens.
- Seed data in `database.sql` duplicates types per company; never copy rows across tenants without setting `company_id`.

## 9. Audit Logging Requirements
MySQL triggers on `idf_device_type` insert audit rows on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`). Session actor columns come from `config.php` (`@app_user_id`, `@app_username`, etc.).

| Trigger | Actions | Payload highlights |
|---------|---------|-------------------|
| `trg_idf_device_type_audit_insert` | INSERT | `id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, timestamps |
| `trg_idf_device_type_audit_update` | UPDATE | old/new JSON for the same fields |
| `trg_idf_device_type_audit_delete` | DELETE | deleted row JSON |

`table_name` in `audit_logs` is `idf_device_type`; `record_id` is the row `id`.

## 10. Common Pitfalls
- **Protection Zone:** do not refactor or extend this module unless explicitly requested.
- **Deleting in-use types:** `ON DELETE RESTRICT` from `idf_positions` blocks deletion — reassign positions in `modules/idfs/` or `idf_positions` first.
- **IDF sync:** adding or renaming types does not automatically fix port rows; position save/regenerate paths in `modules/idfs/api/` own port counts. After any cross-module IDF change, run `php scripts/idfs_sync_human_test.php`.
- **Do not update only one entry file:** fixes in `index.php` must be mirrored in wrapper files when shared blocks change (`create.php`, `edit.php`, `view.php`, `list_all.php`, `delete.php`).
- **Search column alias:** ensure `$displayFieldColumns = $uiColumns` exists before the search `foreach` when using `$displayFieldColumns`.

## 11. Examples of Safe Code Patterns

### Safe SELECT (list types for active company)
```php
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, idfdevicetype_name, field_edit_emoji, active
     FROM idf_device_type
     WHERE company_id = ?
     ORDER BY idfdevicetype_name'
);
mysqli_stmt_bind_param($stmt, 'i', $company_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
```

### Safe INSERT (new device type)
```php
$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO idf_device_type (company_id, idfdevicetype_name, field_edit_emoji, active)
     VALUES (?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'issi', $company_id, $type_name, $emoji, $active);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

## 12. Module Owner Notes (Optional)
Seeded types per company include switch, patch_panel, ups, server, and other with default emojis. Rack layout and port generation logic lives in `modules/idfs/`; this module is the lookup source only.
