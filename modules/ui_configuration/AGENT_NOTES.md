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

- **Standard flattened CRUD** via `edit.php`.
- **active field** is added as a hidden field (defaults to 1) and is excluded from visible list/view tables.
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
