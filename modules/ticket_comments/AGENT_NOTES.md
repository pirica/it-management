# AGENT_NOTES.md - Ticket Comments

## 1. Module Purpose

CRUD for **`ticket_comments`** — threaded notes on support tickets (`ticket_id`, `employee_id`, `body`, internal flag). Primary UX is create/edit from the tickets module; this folder is the flattened scaffold list/view/admin surface.

## 2. Key Tables

- **ticket_comments** — comment body and metadata per ticket.
- **tickets** — parent FK (`ticket_id`).
- **employees** — author FK (`employee_id`); `@mention` picker loads tenant employees.

## 3. Required Relationships

- **ticket_comments.company_id** → **companies** (`ON DELETE` via FK)
- **ticket_comments.ticket_id** → **tickets**
- **ticket_comments.employee_id** → **employees**

## 4. Business Rules (Critical for Agents)

- **`is_internal`**: when `1`, comment is staff-only (not shown to external/requestor flows that respect the flag).
- **@mentions:** on create/edit save, `itm_notify_ticket_comment_mentions()` notifies newly mentioned `@username` targets; edit passes previous body so only new mentions fire.
- **Mention UI:** F2 user picker on `body` textarea (`js/ticket-comment-mentions.js`).

## 5. UI Behavior Requirements

- Standard flattened CRUD via `index.php`; wrappers (`view.php`, `edit.php`, `list_all.php`) set `$crud_action` and require `index.php`.
- Hide **`company_id`** from list/view/forms (`$hideCompanyIdTables` includes `ticket_comments`).
- **`is_internal`** on list/view renders ✅/❌ via `cr_render_cell_value()` in `index.php` and `create.php`; create/edit use checkbox double-label pattern.
- **`active`**: list/view Active/Inactive badges (audit scaffold); not mixed with `is_internal` emoji.

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php` when enabled.
- **create_share_session** — via `itm_crud_record_share_handle_ajax_request()` on `index.php`.

## 7. File Structure

- **index.php** — list, view, edit, import, mention notify on save
- **create.php** — create flow with duplicated `cr_render_cell_value()`
- **delete.php**, **view.php**, **edit.php**, **list_all.php** — standard wrappers

## 8. Multi-Tenant Rules

- All queries scoped by session **`company_id`**.

## 9. Audit Logging Requirements

- **`trg_ticket_comments_audit_insert|update|delete`** in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Do not show raw `0`/`1` for **`is_internal`** on list/view.
- Mention notifications must stay on save paths in `index.php` when editing comment body.

## 11. Examples of Safe Code Patterns

```php
$stmt = $conn->prepare(
    'SELECT id, body, is_internal FROM ticket_comments WHERE company_id = ? AND ticket_id = ? AND deleted_at IS NULL'
);
$stmt->bind_param('ii', $companyId, $ticketId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)

- Parent module: **`modules/tickets/`**
- List: [modules/ticket_comments/index.php](http://localhost/it-management/modules/ticket_comments/index.php) (Admin session, open in a new browser tab)
