# CRUD record share (temporary QR / 6-digit code)

Record-only modules use the unified `share_sessions` table and the shared helper `includes/itm_crud_record_share.php` (same modal/JS as Passwords, Notes, and Explorer share).

## Capability source of truth

- **Implemented slugs:** `itm_qr_share_capable_module_slugs()` in `includes/itm_qr_share.php`
- **Admin matrix:** `modules/share_modules/` — all registry rows; only capable slugs are toggleable; others show **No share UI**
- **Runtime gate:** `has_module_share_access()` in `includes/itm_module_share.php` (called from `itm_qr_share_create_session()`)

## CRUD record rollout (23 modules)

| Slug | Share UI location | Payload |
|------|-------------------|---------|
| `employees` | `view.php` | Bespoke employee profile fields |
| `departments` | `index.php` inline view | Generic `crud_record` from module config |
| `equipment` | `view.php` (record); `index.php?switch_id=&spm=1` (Switch Port Manager) | `crud_record` or `equipment_switch_ports` (`share_kind=switch_ports`) |
| `catalogs` | `index.php` inline view | Generic `crud_record` |
| `license_management` | `index.php` inline view | Generic `crud_record` |
| `inventory_items` | `view.php` | Generic `crud_record` |
| `suppliers` | `index.php` inline view | Generic `crud_record` |
| `alerts` | `index.php` inline view | Bespoke alert fields + assignee |
| `tickets` | `view.php` | Bespoke ticket fields |
| `patches_updates` | `index.php` inline view | Generic `crud_record` |
| `ops_report` | `index.php` toolbar (loaded daily report) | Bespoke ops report snapshot |
| `annual_budgets` | `index.php` inline view | Generic `crud_record` |
| `approvals` | `index.php` inline view | Generic `crud_record` |
| `approvals_stage` | `index.php` inline view | Generic `crud_record` |
| `approver_type` | `index.php` inline view | Generic `crud_record` |
| `approvers` | `index.php` inline view | Generic `crud_record` |
| `budget_categories` | `index.php` inline view | Generic `crud_record` |
| `cost_centers` | `index.php` inline view | Generic `crud_record` |
| `expenses` | `index.php` inline view | Generic `crud_record` |
| `forecast_revisions` | `index.php` inline view | Generic `crud_record` |
| `forecast_revisions_status` | `index.php` inline view | Generic `crud_record` |
| `gl_accounts` | `index.php` inline view | Generic `crud_record` |
| `monthly_budgets` | `index.php` inline view | Generic `crud_record` |

**Original share modules (9):** `notes`, `passwords`, `bookmarks`, `todo`, `events`, `private_contacts`, `explorer`, `floor_plans`, `rack_planner` — each uses module-specific `*_share_helpers.php` (not `itm_crud_record_share.php`).

## Intentionally not implemented

| Reason | Slugs |
|--------|--------|
| No record snapshot / dashboard-only | `calendar`, `reports`, `expiring`, `org_chart`, `birthdays` |
| No `view.php` record screen | `contacts`, `resignations`, `budget_report` |
| Child / lookup registry rows (parent has share) | `bookmark_folders`, `note_labels`, `password_entries`, `password_folders`, `todo_categories`, `event_categories`, floor-plan child tables, etc. |

## Wiring contract (per capable CRUD module)

1. **`join.php`** — `itm_crud_record_share_render_join_page($conn, '{slug}')`
2. **`index.php`** — after `config.php`: `itm_crud_record_share_handle_ajax_request($conn, '{slug}')`
3. **Share buttons** — `itm_crud_record_share_render_action_buttons()` on view or inline view block
4. **Modal** — `itm_crud_record_share_include_modal()` before `</body>`
5. **AJAX URL** — `index.php?ajax_action=create_share_session` (even from standalone `view.php`)

Maintenance bulk-wiring: `php scripts/apply_crud_record_share_modules.php --apply`

## Database seeds

Fresh import: `db/02_data.sql` seeds `company_module_share` for all capable slugs × active companies.

Existing databases: `db/migrations/company_module_share_capable_seed.sql`

## Regression

- `php scripts/verify_qr_share_modules.php` — vault/explorer modules + `departments` CRUD record probe
- `php scripts/verify_module_share.php` — `company_module_share` matrix + `has_module_share_access()`
