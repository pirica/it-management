# AGENT_NOTES.md - Switch Status

## 1. Module Purpose
Lookup table for switch port operational status (e.g. Up, Down, Unknown) with optional colour FK for port UI.

## 2. Key Tables
- **switch_status** — status name per company.
- **colors** / **cable_colors** — via `color_id` FK for hex swatches on ports.

## 3. Required Relationships
- **switch_status** → **companies**.
- **switch_status** → referenced by **switch_ports**, **idf_ports**.

## 4. Business Rules (Critical for Agents)
- **FK persistence:** edit forms must keep saved `color_id` selected even when tenant-scoped colour query omits the row — append/load saved value; never fall back to `-- Select --` for existing records.
- **Swatch rendering:** resolve `hex_color` with company-scoped lookup first (`id` + `company_id`), then id-only fallback for legacy/shared rows.
- **Unknown default:** IDF unlink flows reset ports to tenant Unknown + Gray (`#808080`) where applicable.
- Align `index.php`, `edit.php`, and `view.php` for FK/colour preview behaviour.

## 5. UI Behavior Requirements
- Standard flattened CRUD; colour swatch on list/detail when `color_id` set.
- Edit forms use `itm-checkbox-control` pattern for `active`.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — bulk import when enabled.

## 7. File Structure
- `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- `trg_switch_status_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Showing raw `color_id` instead of colour name/hex when label row exists. [Cursor-Valid]
- Dropping persisted FK on edit when company-scoped options incomplete. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Tenant-scoped colour lookup with legacy fallback
```php
$stmt = $conn->prepare('SELECT hex_color FROM cable_colors WHERE id = ? AND company_id = ? LIMIT 1');
$stmt->bind_param('ii', $colorId, $companyId);
// if no row: fallback SELECT hex_color FROM cable_colors WHERE id = ? LIMIT 1
```

## 12. Module Owner Notes (Optional)
Switch port manager icons use status name (case-insensitive Unknown check) — see AGENTS.md equipment port tiles.
