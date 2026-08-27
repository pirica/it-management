# AGENT_NOTES.md - Tickets

## 1. Module Purpose
The central helpdesk/ticketing module for managing support requests.

## 2. Key Tables
- **tickets** — main ticket storage.
- **ticket_settings** — one row per company: survey auto-issue (default off), survey email on issue, SLA on create (`includes/itm_ticket_settings.php`).

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
- **Merge vs problem link:** `merge.php` consolidates duplicate tickets; **Problem Management** links live incidents via `problem_ticket_links` (`modules/problems/`, `includes/itm_problem_management.php`). `view.php` shows related problems, known-error suggestions, and create-from-ticket; `create.php` debounces suggest API. Activity: `problem_linked`, `problem_unlinked`, `known_error_applied`. Doc: `docs/PROBLEM_MANAGEMENT.md`.
- **Master ticket (read-only):** derived from linked problems (`problems.master_ticket_id` via `problem_ticket_links`). Shown on list (`master_ticket_id` column) and view (`Master Ticket` row) — not on create/edit forms. Helper: `itm_ticket_resolve_master_ticket_id()`.
- **Master rollup incident view:** cross-company read-only [`master_view.php`](http://localhost/it-management/modules/tickets/master_view.php) — requires `?id=` + `?company_id=` (not session company). Linked from [master_tickets/view.php](http://localhost/it-management/modules/master_tickets/view.php) incidents via `itm_ticket_master_view_page_href()`. Tenant CRUD stays on `view.php` (session `company_id` only).
- **Photos**: `tickets_photos` stores JSON filename list under `tickets_photos/` upload tree.
- **Search vs archive filter**: when `?search=` is set, archive filter may include both active and archived rows (see `index.php`).
- **Add sample data:** `itm_seed_insert_tickets_sample_row()` in `includes/itm_sample_data_seed.php` (via `sample_seed_helpers.php` for parent seed + repair); **`itm_seed_ensure_tickets_lookup_parents()`** upserts canonical category/status/priority rows even when generic fallback lookup rows already exist; **`itm_seed_sync_mysql_audit_session_for_company()`** re-stamps `@app_company_id` to the target tenant before lookup/ticket INSERT so audit triggers do not fail on stale session company ids. Empty gate uses **`tickets_tenant_active_row_count()`** (`deleted_at IS NULL` and `is_archived = 0`). Resolves `created_by_employee_id` from tenant Admin or the signed-in session employee when the tenant has no local employees. Sample inserts force **`is_archived = 0`**; legacy archived sample rows are repaired on list load and before POST via **`tickets_repair_invisible_sample_rows()`**. Regression: `php scripts/verify_tickets_sample_data.php`.

## 5. UI Behavior Requirements
- **View audit meta:** Detail view lists all six scaffold audit columns (`deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) with employee names and `d-m-Y - H:i:s` timestamps; list hides meta fields. Employment/equipment/ticket **status** badges are separate from row `active` (soft-delete mirror).
- **Standard CRUD** with FK label columns (`status_name`, `priority_name`, etc.).
- **Photo Upload**: Supports uploading photos/screenshots for troubleshooting.
- **Search & Filter**: Extensive filtering by status, priority, assigned user; `show_archived=1` view. **Due from / Due to** use `itm_render_uk_date_input()` (dd/mmm/yyyy text + 📅 picker; `js/itm-uk-date-input.js`); server parses via `itm_parse_date_input()` in `itm_tickets_list_parse_filters()`. List sort uses `$sort` / `$dir` GET params with `$sortSql` in `ORDER BY` (static UI audit contract). Shared list filters: `includes/itm_tickets_list_query.php` (`itm_tickets_list_parse_filters()`).
- **Saved report views:** 💾 save control on search row (`includes/itm_saved_reports_ui.php`); filters use `data-itm-saved-report-filter`. Saved views restore via `saved_view_id` query param (`itm_saved_reports_build_list_url()`). Doc: `docs/SAVED_REPORT_VIEWS.md`.
- **Ticket surveys:** Issue from view (`issue_ticket_survey`); auto-issue on closed status only when enabled on **Configuration** tab (`ticket_settings.auto_issue_survey_on_close`, default off). Tab: [index.php?tab=configuration](http://localhost/it-management/modules/tickets/index.php?tab=configuration). Public submit: `ticket-survey.php?token=`. List filters `survey_status` / `csat_min`; merge cancels pending surveys on source ticket. Doc: `docs/TICKET_SURVEYS.md`. Regression: `php scripts/verify_ticket_surveys.php`.
- **Activity feed:** [view.php](http://localhost/it-management/modules/tickets/view.php) **Activity** card merges `ticket_comments` + `ticket_activity` via `itm_ticket_unified_activity_feed()`; AJAX add comment via [modules/tickets/api.php?action=add_comment](http://localhost/it-management/modules/tickets/api.php?action=add_comment) (`js/ticket-activity-comments.js`) with optional `comment_photo[]` attachments (`ticket_comments.photos_json`). Non-JS fallback: POST `add_ticket_comment` on view. **Edit:** `description` is read-only on [create.php](http://localhost/it-management/modules/tickets/create.php) (edit mode) — conversation updates belong in Activity comments. Edit/archive log `status_changed`, `priority_changed`, `assigned`, `archived` / `unarchived`. Regression: `php scripts/verify_ticket_activity.php`.
- **Archive toggle**: `archive.php` POST sets `is_archived` 0/1 with company scope.
- **Bulk toolbar:** when `$totalRows >= $perPage`, include `bulk-delete-selection.js` and `data-itm-bulk-cancel="1"` Cancel in `index.php` HTML.
- **Create/edit audit scrape:** business **Created By** (`created_by_employee_id`) and **ticket logged-at** (`created_at`) are editable on `create.php` — not scaffold audit columns; reviewed in `scripts/data/fields_missing_reviewed.json`.
- **Assignee notifications:** `itm_notify_ticket_assigned()` on create and when `assigned_to_employee_id` changes on edit (`create.php`). Edit form posts hidden `id` when the query string is omitted.
- **SLA:** On create, `itm_ticket_sla_apply_on_create()` stamps `sla_response_due_at` / `sla_resolve_due_at` from `ticket_sla_policies` when **Configuration → SLA on new tickets** is enabled (`ticket_settings.sla_enabled_on_create`, default on). List/view show `itm_ticket_sla_render_badge()` (green/amber/red). Breach stamps via cron `scripts/run_ticket_sla_monitor.php` → `sla_*_breached_at`. Dashboard: `modules/ticket_sla_dashboard/`. Doc: `docs/TICKET_SLA_DASHBOARD.md`.
- **Inbound email:** `companies.email` is the tenant routing To address. Cron runner `php scripts/run_inbound_email_tickets.php` polls the default SMTP profile when `email_smtp_configurations.inbound_ticket_enabled = 1`. New mail creates tickets via `itm_live_chat_create_ticket()` (default status **Open**, not lowest `ticket_statuses.id`). Threading: `TCK-####` / `[#id]` in subject/body, `In-Reply-To` / `References` Message-ID lookup, and `Re:`/`Fwd:` subject match against open ticket titles **scoped to the sender** (prior inbound `from_email`, assignee, comments, or unambiguous `created_by` only). Keyword rules in `ticket_inbound_email_routing_rules` set `priority_id`, `category_id`, and `assigned_to_employee_id` on new tickets (`urgent`, `critical`, `billing`, `support` seeds). All inbound events log to `emails` (`status` `received`/`failed`, JSON `details` with `inbound_event` + `raw_payload`). Dedupe: `ticket_inbound_email_messages`. Core: `includes/itm_inbound_email_tickets.php`. Regression: [verify_inbound_email_tickets.php?run=1](http://localhost/it-management/scripts/verify_inbound_email_tickets.php?run=1).

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import on `index.php`.
- **archive.php** — POST archive/unarchive by ticket `id` + `company_id`.

## 7. File Structure
- Standard CRUD structure + `archive.php`.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- `trg_tickets_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls
- Soft-deleting tickets that should only be archived — use `archive.php` for archive/restore; soft-delete is for delete/bulk/clear. [Cursor-Valid]
- Listing raw `status_id` / `assigned_to_employee_id` when label rows exist. [Cursor-Valid]
- Runtime `SHOW COLUMNS` / `ALTER TABLE` for `tickets.is_archived` — removed; column is in `db/03_triggers.sql` `CREATE TABLE`. Do not re-add per-request schema mutation. [Cursor-Fixed]
- Photo paths must use `ticket_photo_public_path()` / upload helpers, not raw `../../tickets_photos/` assumptions. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM tickets WHERE company_id = ? AND status_id = ?");
$stmt->bind_param("ii", $companyId, $statusId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
- **`db/` seeds:** one `TCK-0001` row per seeded company (ids 1–5), each with tenant `Open` `status_id`. The late `@replicate_source_company_id` block does **not** copy `tickets` — explicit per-company inserts are the only seed rows (avoids duplicate open tickets on import).
The primary interface for IT support operations.
## Share (temporary QR / code)
- **Capable:** `itm_qr_share_capable_module_slugs()`.
- **UI:** Share buttons on view.php.
- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.
- **Doc:** `docs/CRUD_RECORD_SHARE.md`.
