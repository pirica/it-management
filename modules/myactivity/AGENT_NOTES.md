# AGENT_NOTES.md - My Activity

## 1. Module Purpose

Read-only **employee-scoped** audit timeline for the signed-in user. Surfaces the same `audit_logs` rows shown in **user-config.php → Recent Activity**, with filters, pagination, detail view, and PDF/Excel export. Not a duplicate of **Audit Logs** (`modules/audit_logs/`) — admins see all company events there; **My Activity** shows only `employee_id = $_SESSION['employee_id']`.

## 2. Key Tables

- **audit_logs** — read-only source (`SELECT` only; no inserts from this module)
- **attempts** — not included (audit trail only; login history remains on user-config Recent Activity widget)

## 3. Required Relationships

- **audit_logs** → `company_id`, `employee_id` (session-scoped filters on every query)
- **modules_registry** slug `myactivity` — company gate via `company_module_access`
- Private-data tables excluded at source (no audit triggers) — same policy as Audit Logs

## 4. Business Rules (Critical for Agents)

- **Employee scope (hard):** every query must include `audit_logs.employee_id = ?` bound to session `employee_id` and `company_id = ?`. `view.php` must reject rows owned by another employee even with a crafted `id`.
- **Read-only:** no create, edit, delete, or import. No `audit_logs` mutations.
- **Settings gate:** when `ui_configuration.enable_audit_logs = 0`, both `index.php` and `view.php` return HTTP 403 (same as Audit Logs).
- **RBAC exempt:** slug `myactivity` in `itm_crud_rbac_exempt_module_slugs()` — any signed-in tenant user with module access may open their own activity.
- **Filters:** module (`table_name`), action (`INSERT` / `UPDATE` / `DELETE`), date from, date to.
- **Exports:** PDF and Excel via shared `table-tools.js` on the activity list table only (`data-itm-no-import-excel`); filter card opts out of export.
- **Module links:** `table_name` links resolve through `myactivity_resolve_module_href()` (sidebar catalog → `modules/{slug}/`), matching user-config Recent Activity behaviour.

## 5. UI Behavior Requirements

- **index.php** — filter card + **🕒 Recent Activity** timeline (user-config style) + paginated **Activity list** table with `itm-actions-cell` / View 🔎; intro note states private vault modules are **never audited** (no `audit_logs` anywhere — per `AGENTS.md` **Private data — no audit trail**)
- **view.php** — full event detail (action, module, record id, IP, user agent, old/new JSON payloads)
- Sidebar: **👤 Employee** section → **🕒 My Activity** (`includes/ui_config.php`)
- Dynamic browser title via `itm_resolve_module_sidebar_icon()`
- Pagination: emoji-only ⏮️ ◀️ ▶️ ⏭️ when `records_per_page` exceeded

## 6. API Actions (If Applicable)

None.

## 7. File Structure

- **index.php** — filtered list + timeline + export table
- **view.php** — single audit row detail
- **index.html** — directory listing guard
- **includes/itm_myactivity.php** — shared query/filter helpers and module href resolver

## 8. Multi-Tenant Rules

- Requires signed-in session, active `company_id`, and `employee_id`
- Data limited to the current employee’s audit rows within the active company
- `company_module_access` must allow slug `myactivity` (opt-out policy)

## 9. Audit Logging Requirements

No writes — consumer only.

## 10. Common Pitfalls

- Do **not** drop the `employee_id` predicate when adding filters or exports
- Do not expose other users’ `audit_logs` rows on `view.php?id=`
- Private-data tables (`passwords`, `notes`, `bookmarks`, `private_contacts`, `todo`, `events`, `emails` send log, etc.) have **no** `audit_logs` rows and **no** audit triggers — they never appear in My Activity or Audit Logs. UI intro copy must say **never audited / 100% private**, not “not listed here”.
- Registry row lives in `db/02_data.sql`; run `php scripts/sync_modules_registry.php` on existing DBs after deploy

## 11. Examples of Safe Code Patterns

```php
$stmt = mysqli_prepare(
    $conn,
    'SELECT al.* FROM audit_logs al WHERE al.id = ? AND al.company_id = ? AND al.employee_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'iii', $auditId, $companyId, $employeeId);
```

## 12. Module Owner Notes (Optional)

- Complements **user-config.php** Recent Activity (last 10, mixed with login attempts) and **modules/audit_logs/** (admin company-wide trail).
