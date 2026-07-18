# AGENT_NOTES.md - UI Configuration

## 1. Module Purpose

Manages per-company and per-employee UI layout preferences (such as action button positions, back/save positions, records per page, chatbot toggle) and system-access/API key rate limit tier configurations.

## 2. Key Tables

- **ui_configuration** — stores user preferences, chatbot options, and API key limits

## 3. Required Relationships

- **ui_configuration** → depends on **companies** (`company_id`, ON DELETE CASCADE)
- **ui_configuration** → depends on **employees** (`employee_id`, ON DELETE CASCADE)

## 4. Business Rules (Critical for Agents)

- All queries must be strictly scoped to the logged-in employee (`employee_id = $_SESSION['employee_id']` / `company_id = $_SESSION['company_id']`) to maintain individual preferences and private API keys.
- **API rate limits:** keyless requests are only allowed for active sessions on the Free tier. Paid tiers (Basic, Pro, Enterprise) require a valid `api_key` and enforce sliding-window quotas.
- **Fresh-import seeds:** one default row per company bound to **that company’s** seed Admin (`INSERT … SELECT` from `employees` where `work_email LIKE 'admin@techcorp.example%.com'`). Do not set `employee_id = 1` for companies 2–5.
- **Cross-company replicate:** copies TechCorp UI defaults onto other tenants by matching `username` on the target company (and sets `favicon_path` to `company_{id}`); never reuse TechCorp `employee_id` on foreign `company_id` values.

## 5. UI Behavior Requirements

- **Standard flattened CRUD** via `edit.php` (wrapper → `index.php`).
- **API key / rate-limit fields** (`api_key`, `api_key_is_active`, `api_key_last_used_at`, `rate_limit_*`, `tier`) are **excluded** from scaffold create/edit/list forms — manage keys on **Settings → API Access**. `cr_is_hidden_ui_configuration_field()` must not rely on `$GLOBALS['crud_table']` (unset in this module). Empty `NOT NULL` varchar fields (e.g. `favicon_path`) persist as `''`, not SQL `NULL`.
- **active field** is hidden from scaffold forms (defaults apply on create; unchanged on edit).
- Table actions and button positions are dynamically re-ordered globally via `js/ui-layout.js` based on `table_actions_position` and `new_button_position` settings.

## 6. API Actions (If Applicable)

- `import_excel_rows` — JSON POST on `index.php` (standard scaffold).

## 7. File Structure

- **index.php** — main controller and routing hub.
- **create.php**, **edit.php**, **view.php**, **delete.php**, **list_all.php** — standard action wrappers.

## 8. Multi-Tenant Rules

- Queries are scoped to `company_id` first and then individual `employee_id` for personalized experiences.

## 9. Audit Logging Requirements

Unconditional database triggers log DML actions to `audit_logs`:
- `trg_ui_configuration_audit_insert`
- `trg_ui_configuration_audit_update`
- `trg_ui_configuration_audit_delete`

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]

- Hardcoding a fallback company ID instead of using the active session. [Cursor-Valid]
- Displaying the raw `api_key` or `active` fields in visible list screens when they are meant to be secured or hidden. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM ui_configuration WHERE company_id = ? AND employee_id = ? LIMIT 1");
$stmt->bind_param("ii", $companyId, $employeeId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for rendering personalized workspace settings and verifying API quotas.
