# AGENT_NOTES.md - Backup Tape Log

## 1. Module Purpose
Manages a monthly grid view to track server backup tapes. It allows users to record when tapes are inserted and returned to the safe.

## 2. Key Tables
- **backup_tape_log** — tracks status and timestamps for tapes.

## 3. Required Relationships
- **backup_tape_log** → depends on **companies**.
- **backup_tape_log** → depends on **equipment** (via `server_id`, restricted to `equipment_type` = 'Server').

## 4. Business Rules (Critical for Agents)
- **Monthly grid:** one row per day of selected month/year/server; `log_date` and `tape_to_be_used` (day name) auto-derived.
- **Sunday highlighting:** Sunday rows highlighted in yellow on the grid.
- **Immutability:** records not from **today** locked for edit/delete.
- **Restricted fields:** `tape_used_for_restore` and `ism_review` editable only by Admin or IT department staff.
- **Role-Based Access:** Admin and IT staff full access; regular users may have restricted fields/dates.
- **Date Logic:** `btl_format_datetime` treats `1970-01-01` as "—" for display.
- **Exports:** XLSX and PDF must include custom header (Year, Month, Company, Server, Unit No) and grid layout.

## 5. UI Behavior Requirements
- **Grid View**: A custom interactive grid instead of a standard list.
- **Time Punch**: A "⌛" icon is used to auto-fill the current time into timestamp fields.
- **AJAX Updates**: Supports inline editing via POST requests.

## 6. API Actions (If Applicable)
- **ajax_inline_edit** — Handles async updates to status and timestamps.

## 7. File Structure
- **index.php** — main monthly grid and AJAX handler.
- **edit.php**, **view.php**, **delete.php**, **list_all.php** — standard CRUD support.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.
- Filters equipment by `company_id` and 'Server' type.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Server Selection**: Ensure `server_id` is valid and belongs to the correct company.
- **Date Overlap**: Ensure only one record exists per server, company, and date.

## 11. Examples of Safe Code Patterns

### Safe SELECT (Monthly)
```php
$stmt = $conn->prepare("SELECT * FROM backup_tape_log WHERE server_id = ? AND log_date BETWEEN ? AND ? AND company_id = ?");
$stmt->bind_param("issi", $serverId, $startDate, $endDate, $companyId);
$stmt->execute();
```

### Safe AJAX Update
```php
$stmt = $conn->prepare("UPDATE backup_tape_log SET $field = ? WHERE id = ? AND company_id = ?");
$stmt->bind_param("sii", $value, $id, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The "Time returned to safe" field is critical for ISM compliance.
