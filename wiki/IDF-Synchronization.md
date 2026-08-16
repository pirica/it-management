# IDF Synchronization

Rack planner and IDF workflows must keep multiple tables consistent. These rules mirror `AGENTS.md` and apply to changes in:

- `modules/idfs/view.php`
- `modules/idfs/device.php`
- `modules/equipment/`
- `modules/switch_ports/`

> This page documents required IDF/equipment synchronization behavior for authorized changes.

## Tables that must stay in sync

On every **Create, Edit, Update, Delete, Copy, and Move** that touches rack or device state, keep these aligned:

| Table | Role |
|-------|------|
| `idfs` | IDF record |
| `idf_positions` | Rack positions |
| `idf_ports` | Port rows on IDF side |
| `idf_links` | Links between positions/ports |
| `idf_device_type` | Device type metadata |
| `switch_ports` | Mirrored switch port rows |
| `equipment` | Equipment records (`idf_id` alignment) |

## Hard-fail policy

- **No partial cross-table updates.** If a workflow touches multiple IDF-related tables, use **transactions** and **rollback** on any synchronization failure.
- A PR is not complete if linked rows disagree after an operation.

## Operation-specific rules

### Link and port actions

`link_create`, `port_update`, `link_delete`, `position_save`, `position_delete`, `position_copy`, and move/reorder actions must preserve **status, color, label, and notes** parity across linked `idf_ports` and mirrored `switch_ports` rows.

### Delete, overwrite, and move

- Clean up or update dependent `idf_links` and `idf_ports` **before** deleting or replacing `idf_positions`.
- Keep `equipment.idf_id` and `switch_ports.idf_id` aligned with the active IDF link state.

### Unknown reset

Unlink and delete flows must reset synchronized ports to tenant defaults where applicable:

- Status: **Unknown**
- Color: **Gray** (`#808080`)

## High-density racks

- Rack position validation supports up to **250** positions.
- Batch move/reorder uses temporary offsets (**1000**) to avoid unique constraint collisions during reorder.

## Switch ports wrapper modules

If `create.php`, `edit.php`, `view.php`, `delete.php`, or `list_all.php` set `$crud_action` and require `index.php`, then **`index.php` must not overwrite** wrapper-provided `$crud_action` values.

## Mandatory human-flow regression test

Run from the **repository root** after any affected Create/Edit/Update/Delete/Copy/Move change:

**Linux, macOS, CI (PHP on PATH):**

```bash
php scripts/idfs_sync_human_test.php
```

**Windows Laragon (when `php` is not on PATH):**

```text
<laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\idfs_sync_human_test.php
```

Use **PHP 7.4.33** with **MySQLi** enabled. Optional environment variables are documented in `scripts/idfs_sync_human_test.php` (`ITM_BASE_URL`, `ITM_USER`, `ITM_PASS`, database settings).

**Hard fail:** if the script reports `[FAIL]`, the task is not complete.

## Pre-commit checklist

- [ ] All touched workflows use transactions where multiple IDF tables update
- [ ] `idf_links` / `idf_ports` cleaned up before position delete/replace
- [ ] `equipment.idf_id` and `switch_ports.idf_id` match link state
- [ ] Port parity (status, color, label, notes) across `idf_ports` and `switch_ports`
- [ ] Unlink/delete resets Unknown + `#808080` where required
- [ ] `php scripts/idfs_sync_human_test.php` passes (or Laragon equivalent)
- [ ] `scripts/api.php` updated if behavior or endpoints changed

## Related documentation

- [Foreign Keys & Display](Foreign-Keys) — label rendering for linked equipment and ports
- [Import Excel](Import-Excel) — `idfs` and related modules with JSON import
- [Security & Audits](Security)
- Repository source: `AGENTS.md` (IDF synchronization guardrail)
