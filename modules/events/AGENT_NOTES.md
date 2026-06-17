# AGENT_NOTES.md - Events

## 1. Module Purpose
Manages scheduled events, meetings, and maintenance windows.

## 2. Key Tables
- **events** — main event data.

## 3. Required Relationships
- **events** → depends on **companies**.
- **events** → depends on **event_categories**.
- **events** → links to **users** (via `assigned_to_user_id`).

## 4. Business Rules (Critical for Agents)
- **Date Validation**: `start_datetime` should generally be before `end_datetime`.
- **Visibility**: Similar to alerts, may have assignment-based visibility logic depending on the implementation.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: POST handlers use `itm_require_post_csrf()`; forms include hidden `csrf_token`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

- **ICS Export**: Often supports exporting to iCalendar format.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_events_audit_insert`, `trg_events_audit_update`, `trg_events_audit_delete` on `events` in `database.sql` write to `audit_logs` when `enable_audit_logs` is enabled.

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first.
- Integrated into **calendar**; uses **event_categories**.
- Respect tenant unique constraints; duplicates fail at the database layer.
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM events WHERE company_id = ? AND start_datetime >= ?");
$stmt->bind_param("is", $companyId, $startDate);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO events (company_id, title, start_datetime, active) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $companyId, $title, $startDatetime, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary source of data for the Calendar module.
