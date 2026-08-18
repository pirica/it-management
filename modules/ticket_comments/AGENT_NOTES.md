# AGENT_NOTES.md - Ticket Comments

## 1. Module Purpose

CRUD for **`ticket_comments`** — notes on support tickets (`ticket_id`, `employee_id`, `body`, internal flag). Primary UX is create/edit from the tickets module; this folder is the flattened scaffold list/view/admin surface.

## 2. Key Tables

- **ticket_comments** — comment body and metadata per ticket.
- **tickets** — parent FK (`ticket_id`); list/view show ticket **title**, not raw id.
- **employees** — author FK (`employee_id`); list/view show **first_name + last_name** (username fallback).

## 3. Required Relationships

- **ticket_comments.company_id** → **companies**
- **ticket_comments.ticket_id** → **tickets**
- **ticket_comments.employee_id** → **employees**

## 4. Business Rules (Critical for Agents)

- **`is_internal`**: when `1`, comment is staff-only where downstream flows respect the flag.
- **@mentions:** on create/edit save, `itm_notify_ticket_comment_mentions()` notifies newly mentioned `@username` targets; edit compares against previous body.
- **Mention UI:** F2 user picker on `body` (`js/ticket-comment-mentions.js`).

## 5. UI Behavior Requirements

- Standard flattened CRUD via `index.php`; wrappers (`view.php`, `edit.php`, `list_all.php`) require `index.php`.
- Hide **`company_id`** from list/view/forms (`$hideCompanyIdTables` includes `ticket_comments`).
- **FK labels (mandatory):** `ticket_id` and `employee_id` must not show raw numeric IDs on list/view — `cr_fk_label_for_id()` + `$GLOBALS['fkMap']` in `cr_render_cell_value()` (`index.php` and duplicated `create.php`).
- **`is_internal`**: list/view ✅/❌; create/edit checkbox double-label.
- **`active`**: list/view Active/Inactive badges.

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php`.
- **create_share_session** — via `itm_crud_record_share_handle_ajax_request()` on `index.php`.

## 7. File Structure

- **index.php** — list, view, edit, import, mention notify on save
- **create.php** — create flow (duplicated cell renderer + FK helpers)
- **delete.php**, **view.php**, **edit.php**, **list_all.php** — standard wrappers

## 8. Multi-Tenant Rules

- All queries scoped by session **`company_id`**.

## 9. Audit Logging Requirements

- **`trg_ticket_comments_audit_insert|update|delete`** in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Do not leave **`ticket_id`** / **`employee_id`** as raw FK ids on list or view.
- Do not show raw `0`/`1` for **`is_internal`** on list/view.
- Keep mention notify hooks on save when editing `body`.

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
- Notifications: `docs/NOTIFICATIONS.md`
- List: [modules/ticket_comments/index.php](http://localhost/it-management/modules/ticket_comments/index.php) (Admin session, open in a new browser tab)
