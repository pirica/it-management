# AGENT_NOTES.md - Change Requests (CMDB Lite)

## 1. Module Purpose

IT change requests scoped per tenant with blast-radius CI selection from the CMDB impact graph (`includes/itm_cmdb.php`).

## 2. Key Tables

- **change_requests** — header (`source_configuration_item_id`, title, status, schedule)
- **change_request_configuration_items** — affected CI links from impact picker

## 3. Required Relationships

- **change_requests** → **configuration_items** (`source_configuration_item_id`, RESTRICT)
- **change_request_configuration_items** → **change_requests**, **configuration_items** (CASCADE)

## 4. Business Rules (Critical for Agents)

- Source CI is the change target; affected CIs are chosen from BFS impact graph checkboxes on create/edit.
- Status workflow: `draft`, `submitted`, `approved`, `rejected`, `implemented`, `cancelled`.
- Soft-delete only on `change_requests`; junction rows soft-deleted when replaced on save.

## 5. UI Behavior Requirements

- **create.php** / **edit.php** — source CI dropdown + impact graph + CI checklist (`js/itm-cmdb-impact-graph.js`).
- **view.php** — affected CI list + mini impact graph.

## 6. API Actions (If Applicable)

- Impact data via [modules/configuration_items/api.php](http://localhost/it-management/modules/configuration_items/api.php?action=impact&id=1) (`action=impact`).

## 7. File Structure

- **index.php** — list + search; empty-state **Add sample data**; row actions: view, edit, delete (POST soft-delete)
- **create.php** — create/edit form (`edit.php` wrapper)
- **view.php** — affected CI list + mini impact graph; toolbar delete POST to `delete.php`
- **delete.php** — soft-delete handler (CSRF + `company_id` scope)

## 8. Multi-Tenant Rules

- All queries filter `company_id` from session.

## 9. Audit Logging Requirements

- `trg_change_requests_audit_*`, `trg_change_request_configuration_items_audit_*` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Affected CI list must include source CI when blast-radius should cover the target node.
- Dates use dd/mmm/yyyy in forms via `itm_parse_date_input()` / `itm_format_date_display()`.
- Sample data requires configuration items (seeder calls `itm_seed_insert_configuration_items_sample_rows()` when the tenant CI table is empty). Fresh `db/02_data.sql` import seeds demo CIs, relationships, and two change requests per company (1–5).

## 11. Examples of Safe Code Patterns

```php
itm_change_request_replace_affected_cis($conn, $companyId, $changeRequestId, $ciIds, $employeeId);
$affected = itm_change_request_list_affected_rows($conn, $companyId, $changeRequestId);
```

## 12. Module Owner Notes (Optional)

- Helpers: `includes/itm_change_requests.php`
- Migration: `db/migrations/change_requests_cmdb.sql`
