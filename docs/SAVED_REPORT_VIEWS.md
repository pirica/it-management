# Saved Report Views (Custom Report Builder)

Power users save **filter and column presets** from Tickets, Equipment, or Expenses list pages. Saved views appear in Reports Hub **My reports**, can be exported, shared read-only, run as JSON, and scheduled by email.

## Tables

| Table | Role |
|-------|------|
| `saved_report_views` | Per-owner filter/column JSON, `shared_scope`, standard audit columns |
| `scheduled_reports` | Optional `saved_view_id` when `report_slug` is `saved_view:{id}` |
| `share_sessions` | Temporary public read-only links (`module_slug = saved_report_views`) |

### `saved_report_views` columns (summary)

- `company_id`, `employee_id` (owner), `module_slug` (`tickets` \| `equipment` \| `expenses`)
- `name`, `filters_json`, `columns_json`
- `shared_scope` — `private` \| `department` \| `company`
- `share_department_id` — stamped when scope is `department` (owner’s department at save time)

Fresh install: `db/01_schema.sql`. Existing databases: `db/migrations/saved_report_views.sql`.

## Supported modules and whitelisted filters

Validation lives in `includes/itm_saved_reports.php` (`itm_saved_reports_module_config()`). Unknown filter keys are **stripped** — never passed to SQL.

### Tickets

| Filter | Type |
|--------|------|
| `search` | string |
| `show_archived` | bool |
| `sort`, `dir` | sortable column + ASC/DESC |
| `status_id`, `priority_id`, `assigned_to_employee_id` | int |
| `due_date_from`, `due_date_to` | date |
| `survey_status` | string (`pending` \| `completed` \| `none`) |
| `csat_min` | int (minimum CSAT / survey average) |

List query: `includes/itm_tickets_list_query.php`. Optional column `survey_summary` for exports.

### Equipment

| Filter | Type |
|--------|------|
| `search`, `equipment_type_name` | string |
| `sort`, `dir` | sortable column + ASC/DESC |

List query: `includes/itm_equipment_list_query.php`.

### Expenses

| Filter | Type |
|--------|------|
| `search` | string |
| `date_from`, `date_to` | date |
| `paid_status_id`, `supplier_id` | int |
| `sort`, `dir` | sortable column + ASC/DESC |

List query: `includes/itm_expenses_list_query.php`.

## UI workflow

1. Open a supported module list and set filters.
2. Click **💾** on the search row (`includes/itm_saved_reports_ui.php`).
3. Enter name, share scope, and column checkboxes (at least one column).
4. Open [Reports Hub → My reports](http://localhost/it-management/modules/reports/index.php#my-saved-reports) to manage views.

**Restore filters on list:** saved views append `saved_view_id` to list URLs (`itm_saved_reports_build_list_url()`). Banner: `includes/itm_saved_reports_list_banner.php`.

## Reports Hub actions (owners vs shared readers)

| Action | Owner | Shared reader |
|--------|-------|----------------|
| Open list with filters | Yes | Yes |
| Run JSON API | Yes | Yes |
| Export Excel/PDF | Yes | Yes |
| Edit name/scope/columns | Yes | No |
| Share temporary link | Yes | No |
| Schedule email | Yes | No |
| Delete | Yes | No |

Hub modals: `includes/itm_saved_reports_hub_ui.php`.

## API (`modules/saved_report_views/api.php`)

Session + `itm_api_enforce_rate_limit_or_exit()`. CSRF on POST.

| Action | Method | Purpose |
|--------|--------|---------|
| `save` | POST | Create or update (owner only for updates) |
| `delete` | POST | Soft-delete (owner) |
| `list` | GET | Visible views for session employee |
| `get` | GET | One view when `can_view` |
| `run` | GET | JSON rows + total (`limit`/`offset` query params) |
| `share` | POST | Create `share_sessions` token (owner) |

Example: [api.php?action=run&id=1](http://localhost/it-management/modules/saved_report_views/api.php?action=run&id=1) (Admin session).

## Export

[export.php?id=&format=xlsx\|pdf](http://localhost/it-management/modules/saved_report_views/export.php?id=1&format=xlsx) — session + `can_view`; up to 5000 rows (`itm_saved_reports_export_row_limit()`).

## Public share

1. Owner clicks **📱** on Hub or `api.php?action=share` (POST).
2. Recipient opens [join.php?t=](http://localhost/it-management/modules/saved_report_views/join.php) with token or 6-digit code.
3. Live table render (`itm_saved_reports_share_render_live_table()`); 30-minute TTL.

## Scheduled email

- Slug: `saved_view:{id}` (`itm_saved_reports_scheduled_slug()`).
- Owners schedule from Hub modal; admins use executive **Scheduled executive reports** section.
- Runner: `php scripts/run_scheduled_reports.php` loads dataset via `itm_saved_reports_run_query()` + `itm_saved_reports_render_email_dataset()`.
- See also: `docs/SCHEDULED_REPORTS.md`.

## Dashboard

`itm_saved_reports_dashboard_snapshot()` on [dashboard.php](http://localhost/it-management/dashboard.php) — count + preview of recent views.

## Access control

- `itm_saved_reports_can_view()` — owner, `shared_scope = company`, or `department` with matching `share_department_id`.
- `itm_saved_reports_can_manage_schedule_slug()` — owner for `saved_view:{id}`; admins for executive catalog slugs.
- Module slug `saved_report_views` is in `itm_module_access_always_allowed_slugs()` (API invoked from list modules).

## Verification

```bash
php scripts/verify_saved_report_views.php
```

Browser (Admin): [verify_saved_report_views.php?run=1](http://localhost/it-management/scripts/verify_saved_report_views.php?run=1).

## Related files

| Path | Role |
|------|------|
| `includes/itm_saved_reports.php` | Validation, access, query runners, export/share |
| `includes/itm_saved_reports_ui.php` | Save-view modal on list pages |
| `includes/itm_saved_reports_hub_ui.php` | Hub edit + owner schedule modals |
| `includes/itm_saved_reports_list_banner.php` | “Viewing saved report” banner |
| `modules/saved_report_views/` | API, export, join, index redirect |
| `modules/reports/index.php` | `#my-saved-reports` section |

Module notes: `modules/saved_report_views/AGENT_NOTES.md`, `modules/reports/AGENT_NOTES.md`.
