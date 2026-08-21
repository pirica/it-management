# AGENT_NOTES.md - Problem Management

## 1. Module Purpose

Tenant-scoped **Problem Management** records track recurring incidents, root-cause analysis, status workflow (`investigating` → `known_error` → `resolved` / `closed`), and links to related tickets. **Known errors** capture workarounds and optional **Knowledge Base** publish. Shared business logic lives in `includes/itm_problem_management.php`.

## 2. Key Tables

- **problems** — main problem records (`title`, `description`, `root_cause`, `status`, `owner_employee_id`, `knowledge_base_id`, audit columns)
- **problem_ticket_links** — many-to-many problem ↔ ticket incident links (soft-delete via `deleted_at`)
- **known_errors** — one active known-error row per problem (`title`, `workaround`, `symptom_keywords`, optional `knowledge_base_id`)
- **tickets** — linked incidents (read-only in this module; link/unlink via helpers)
- **knowledge_base** — optional publish target when `create_kb` is checked on known-error save

## 3. Required Relationships

- **problems** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **problems** → **employees** (`owner_employee_id`, `ON DELETE SET NULL`)
- **problems** → **knowledge_base** (`knowledge_base_id`, `ON DELETE SET NULL`)
- **problem_ticket_links** → **problems** / **tickets** (`ON DELETE CASCADE`)
- **known_errors** → **problems** (`ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- All reads/writes scope by session `company_id`; hide `company_id` from UI.
- Create/update/delete use `itm_problem_create()`, `itm_problem_update()`, `itm_problem_soft_delete()` — do not hard-delete `problems`.
- Status transitions enforced server-side via `itm_problem_allowed_transitions()`; edit form only lists allowed next statuses.
- Ticket link requires non-merged, non-deleted ticket in the same company (`itm_problem_ticket_is_linkable()`).
- Known-error publish sets problem `status` to `known_error` and optionally upserts **Knowledge Base** (`itm_known_error_upsert()` / `itm_known_error_publish_to_kb()`).
- `create.php?ticket_id=` auto-links the ticket after successful create via `itm_problem_link_ticket()`.
- Automation/webhooks: `itm_problem_dispatch_events()` on create, status change, and known-error publish.

## 5. UI Behavior Requirements

- Flattened list in `index.php` (not auto-scaffold): columns **Title**, **Status** badge (`itm_problem_status_badge`), **Owner** name, **Incidents** count subquery, **Known Error** Yes/No.
- Search/sort/pagination: `$perPage = itm_resolve_records_per_page()`; bulk toolbar when `$totalRows >= $perPage`; `$displayFieldColumns = $uiColumns` before search SQL.
- List table: `data-itm-db-import-endpoint="index.php"`; Actions cells use `itm-actions-cell` + `data-itm-actions-origin="1"`.
- Bulk delete: shared `bulk-delete-form` → `delete.php`; Cancel button `data-itm-bulk-cancel="1"`.
- Emoji-only action buttons (NO MIXED) on View/Edit/Delete/Save/Back/pagination.
- Bespoke **view.php**: detail + audit meta, linked incidents table, link-ticket form, known-error publish form (title, workaround, symptom_keywords, `create_kb` checkbox), per-row unlink.
- Layout matches sidebar/header/content pattern from `modules/ticket_sla_dashboard/index.php`.
- **CSRF:** `itm_get_csrf_token()`, `itm_require_post_csrf()` on POST handlers.

## 6. API Actions (If Applicable)

- **`modules/problems/api.php`**
  - `GET ?action=suggest&title=&description=&limit=` — session auth + `itm_api_enforce_rate_limit_or_exit()`; returns `itm_known_error_suggest_for_ticket()` matches as JSON `{ ok, count, suggestions[] }`.
- **`index.php`** — JSON POST `import_excel_rows` (table-tools Excel import); requires import RBAC + CSRF.

## 7. File Structure

- **index.php** — list, search, sort, pagination, Excel import endpoint
- **create.php** — create form; optional `?ticket_id=` auto-link
- **edit.php** — update form with transition-filtered status select
- **view.php** — detail, incidents, link/unlink ticket, known-error publish
- **delete.php** — single/bulk/clear soft delete via `itm_problem_soft_delete()`
- **list_all.php** — wrapper to `index.php`
- **api.php** — known-error suggestion JSON
- **index.html** — directory listing guard

## 8. Multi-Tenant Rules

- Every query filters `company_id = ?` from session.
- Employee owner dropdown loads active employees for the active company; persisted owner appended on edit when missing from the active list.
- Ticket link/unlink validates ticket belongs to the same company and is not merged.

## 9. Audit Logging Requirements

- **problems**, **problem_ticket_links**, **known_errors** — `trg_*_audit_*` triggers in `db/03_triggers.sql` write to `audit_logs` on INSERT/UPDATE/DELETE (not gated by UI `enable_audit_logs`).
- View screen shows `active`, `created_by` / `updated_by` (employee names), and `*_at` timestamps via `itm_format_audit_timestamp_display()`.

## 10. Common Pitfalls

- Do not bypass `itm_problem_*` helpers for mutations — events, resolved_at stamping, and KB linkage depend on them.
- Known-error suggestion API needs tokenized words ≥ 4 chars (`itm_problem_tokenize_search_text`); short queries return empty suggestions.
- Bulk/clear delete loops `itm_problem_soft_delete()` per row — child `problem_ticket_links` / `known_errors` remain in DB (FK CASCADE only on hard delete; links are soft-unlinked separately when needed).
- Import requires a **Title** column; owner may be employee id or username/full name.

## 11. Examples of Safe Code Patterns

### List with tenant scope

```php
$stmt = mysqli_prepare($conn, 'SELECT id, title, status FROM problems WHERE company_id = ? AND deleted_at IS NULL ORDER BY title ASC');
mysqli_stmt_bind_param($stmt, 'i', $companyId);
mysqli_stmt_execute($stmt);
```

### Link ticket

```php
$result = itm_problem_link_ticket($conn, $companyId, $problemId, $ticketId, $actorEmployeeId);
if (empty($result['ok'])) {
    // handle $result['error']
}
```

## 12. Module Owner Notes (Optional)

- Registry: `modules_registry.module_slug = problems` (seed in `db/02_data.sql`).
- Schema: `db/01_schema.sql` (`problems`, `problem_ticket_links`, `known_errors`); migration `db/migrations/problem_management.sql` for existing DBs.
- Core helpers: `includes/itm_problem_management.php`.
- Manual checks: [Open modules/problems/index.php](http://localhost/it-management/modules/problems/index.php) (Admin session); suggest API: [api.php?action=suggest&title=network&description=outage](http://localhost/it-management/modules/problems/api.php?action=suggest&title=network&description=outage) (Admin session).
