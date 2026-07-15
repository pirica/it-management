# AGENT_NOTES.md - Tickets

## 1. Module Purpose
The central helpdesk/ticketing module for managing support requests.

## 2. Key Tables
- **tickets** — main ticket storage.

## 3. Required Relationships
- **tickets** → depends on **companies**.
- **tickets** → depends on **ticket_categories**.
- **tickets** → depends on **ticket_priorities**.
- **tickets** → depends on **ticket_statuses**.
- **tickets** → links to **employees** (Requester).
- **tickets** → links to **users** (Assigned To).
- **tickets** → links to **equipment** (Related Equipment).

## 4. Business Rules (Critical for Agents)
- **Archiving**: Prefer `is_archived = 1` over hard delete — `archive.php` toggles archive state; list defaults to active tickets (`is_archived = 0`).
- **Equipment Link**: Tickets can be linked to specific equipment for lifecycle and maintenance tracking.
- **Due dates**: `due_date` feeds **calendar** integration when tickets module enabled for company.
- **Photos**: `tickets_photos` stores JSON filename list under `tickets_photos/` upload tree.
- **Search vs archive filter**: when `?search=` is set, archive filter may include both active and archived rows (see `index.php`).

## 5. UI Behavior Requirements
- **Standard CRUD** with FK label columns (`status_name`, `priority_name`, etc.).
- **Photo Upload**: Supports uploading photos/screenshots for troubleshooting.
- **Search & Filter**: Extensive filtering by status, priority, assigned user; `show_archived=1` view.
- **Archive toggle**: `archive.php` POST sets `is_archived` 0/1 with company scope.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import on `index.php`.
- **archive.php** — POST archive/unarchive by ticket `id` + `company_id`.

## 7. File Structure
- Standard CRUD structure + `archive.php`.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- `trg_tickets_audit_insert|update|delete` in `database.sql`.

## 10. Common Pitfalls
- Hard-deleting tickets that should be archived — breaks history and calendar due-date traces. [Cursor-Valid]
- Listing raw `status_id` / `assigned_to_employee_id` when label rows exist. [Cursor-Valid]
- Runtime `SHOW COLUMNS` / `ALTER TABLE` for `tickets.is_archived` — removed; column is in `database.sql` `CREATE TABLE`. Do not re-add per-request schema mutation. [Cursor-Fixed]
- Photo paths must use `ticket_photo_public_path()` / upload helpers, not raw `../../tickets_photos/` assumptions. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM tickets WHERE company_id = ? AND status_id = ?");
$stmt->bind_param("ii", $companyId, $statusId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary interface for IT support operations.
