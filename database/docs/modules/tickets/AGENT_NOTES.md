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
- **Archiving**: Prefer `is_archived = 1` for hide-from-default-list without destroying the row — `archive.php` toggles archive state; list defaults to non-archived tickets (`is_archived = 0`). Soft-delete (delete/bulk/clear) is separate: sets `deleted_at` / `deleted_by` / `active=0` and removes the row from lists while keeping `view.php?id=` reachable.
- **Soft-delete + hidden active:** Business status stays on `status_id` → `ticket_statuses` and is shown on **list/view as status badges**. Row `active` is create/edit hidden `active=1` only (not shown on list/view); soft-delete flips `active=0`. List filters `deleted_at IS NULL`. View lists `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`.
- **Equipment Link**: Tickets can be linked to specific equipment for lifecycle and maintenance tracking.
- **Due dates**: `due_date` feeds **calendar** integration when tickets module enabled for company.
- **Photos**: `tickets_photos` stores JSON filename list under `tickets_photos/` upload tree.
- **Search vs archive filter**: when `?search=` is set, archive filter may include both active and archived rows (see `index.php`).

## 5. UI Behavior Requirements
- **View audit meta:** Detail view lists all six scaffold audit columns (`deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) with employee names and `d-m-Y - H:i:s` timestamps; list hides meta fields. Employment/equipment/ticket **status** badges are separate from row `active` (soft-delete mirror).
- **Standard CRUD** with FK label columns (`status_name`, `priority_name`, etc.).
- **Photo Upload**: Supports uploading photos/screenshots for troubleshooting.
- **Search & Filter**: Extensive filtering by status, priority, assigned user; `show_archived=1` view. List sort uses `$sort` / `$dir` GET params with `$sortSql` in `ORDER BY` (static UI audit contract).
- **Archive toggle**: `archive.php` POST sets `is_archived` 0/1 with company scope.
- **Bulk toolbar:** when `$totalRows >= $perPage`, include `bulk-delete-selection.js` and `data-itm-bulk-cancel="1"` Cancel in `index.php` HTML.
- **Create/edit audit scrape:** business **Created By** (`created_by_employee_id`) and **ticket logged-at** (`created_at`) are editable on `create.php` — not scaffold audit columns; reviewed in `scripts/data/fields_missing_reviewed.json`.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import on `index.php`.
- **archive.php** — POST archive/unarchive by ticket `id` + `company_id`.

## 7. File Structure
- Standard CRUD structure + `archive.php`.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- `trg_tickets_audit_insert|update|delete` in `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`.

## 10. Common Pitfalls
- Soft-deleting tickets that should only be archived — use `archive.php` for archive/restore; soft-delete is for delete/bulk/clear. [Cursor-Valid]
- Listing raw `status_id` / `assigned_to_employee_id` when label rows exist. [Cursor-Valid]
- Runtime `SHOW COLUMNS` / `ALTER TABLE` for `tickets.is_archived` — removed; column is in `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` `CREATE TABLE`. Do not re-add per-request schema mutation. [Cursor-Fixed]
- Photo paths must use `ticket_photo_public_path()` / upload helpers, not raw `../../tickets_photos/` assumptions. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM tickets WHERE company_id = ? AND status_id = ?");
$stmt->bind_param("ii", $companyId, $statusId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
- **`database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` seeds:** one `TCK-0001` row per seeded company (ids 1–5), each with tenant `Open` `status_id`. The late `@replicate_source_company_id` block does **not** copy `tickets` — explicit per-company inserts are the only seed rows (avoids duplicate open tickets on import).
The primary interface for IT support operations.
