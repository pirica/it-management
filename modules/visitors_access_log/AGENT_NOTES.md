# AGENT_NOTES.md - Visitors Access Log

## 1. Module Purpose
Manual visitor entry log for physical IT/office access with quick-add on the index and audit-friendly immutability for historical rows.

## 2. Key Tables
- **visitors_access_log** — visitor name, host, in/out times, authorisation fields, and standard/metadata columns (`active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) which are kept as hidden fields in the form.

## 3. Required Relationships
- **visitors_access_log** → **companies**.
- **visitors_access_log** columns `created_by`, `updated_by`, and `deleted_by` map to employee IDs.

## 4. Business Rules (Critical for Agents)
- **Quick Add:** index view keeps a persistent first row for logging new visitors today.
- **Immutability:** records **not created today** are locked for edit and delete (audit integrity).
- **`val_is_today` helper:** must fall back to `created_at` when `date_time_in` is missing.
- **Restricted fields:** follow module rules for who may edit authorisation / escort fields.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view loops `$viewColumns` (or equivalent field list including all six audit meta columns) and renders values through `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). List/index hide audit meta per soft-delete contract.
- Action-wrapper layout with sidebar/header.
- Standard search where implemented; inline quick-add row always visible on index.
- Historical rows (not today) show read-only cells — no edit/delete actions.
- `tape_used_for_restore` / `ism_review` style restricted fields: only Admin or IT department staff may edit (when implemented on row).

## 6. API Actions (If Applicable)
- **quick_save** / inline edit POST handlers on `index.php` — CSRF required; guarded by `val_is_today()`.
- **import_excel_rows** — if enabled on index, must respect immutability rules for historical dates.

## 7. File Structure
- `index.php` — list + quick-add + immutability guards.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_visitors_access_log_audit_*`.

## 10. Common Pitfalls
- Allowing edit/delete on historical (non-today) rows. [Cursor-Valid]
- Using only `date_time_in` for "today" check when `created_at` is the reliable fallback. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Today check with created_at fallback
```php
function val_is_today($dateTimeStr) {
    $raw = trim((string)$dateTimeStr);
    if ($raw === '') {
        return false;
    }
    return date('Y-m-d', strtotime($raw)) === date('Y-m-d');
}
// Usage: val_is_today($row['date_time_in'] ?? $row['created_at'])
```

## 12. Module Owner Notes (Optional)
Physical security compliance tool.
