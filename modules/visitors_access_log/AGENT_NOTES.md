# AGENT_NOTES.md - Visitors Access Log

## 1. Module Purpose
Manual visitor entry log for physical IT/office access with quick-add on the index and audit-friendly immutability for historical rows.

## 2. Key Tables
- **visitors_access_log** — visitor name, host, in/out times, authorisation fields.

## 3. Required Relationships
- **visitors_access_log** → **companies**.

## 4. Business Rules (Critical for Agents)
- **Quick Add:** index view keeps a persistent first row for logging new visitors today.
- **Immutability:** records **not created today** are locked for edit and delete (audit integrity).
- **`val_is_today` helper:** must fall back to `created_at` when `date_time_in` is missing.
- **Restricted fields:** follow module rules for who may edit authorisation / escort fields.

## 5. UI Behavior Requirements
- Action-wrapper layout with sidebar/header.
- Standard search where implemented; inline quick-add row always visible on index.

## 7. File Structure
- `index.php` — list + quick-add + immutability guards.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_visitors_access_log_audit_*`.

## 10. Common Pitfalls
- Allowing edit/delete on historical (non-today) rows.
- Using only `date_time_in` for "today" check when `created_at` is the reliable fallback.

## 12. Module Owner Notes (Optional)
Physical security compliance tool.
