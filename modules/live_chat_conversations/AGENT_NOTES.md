# AGENT_NOTES.md - Live Chat Conversations

## 1. Module Purpose

Flattened CRUD registry module for `live_chat_conversations` conversation headers (`live_agent` and `chat_with`). Primary UX lives in [modules/live_chat/index.php](http://localhost/it-management/modules/live_chat/index.php); this list is for admin review and MBQA.

## 2. Key Tables

- **live_chat_conversations** — conversation type, ticket link, requester/assignee, status, rating, storage path
- **live_chat_participants** — peer participants for `chat_with` threads
- **live_chat_messages** — message bodies (auditable; not private-data exempt)

## 3. Required Relationships

- **live_chat_conversations** → `companies` (`company_id`)
- **live_chat_conversations** → `tickets` (`ticket_id`, optional; typical for `live_agent`)
- **live_chat_conversations** → `employees` (`requester_employee_id`, `assigned_to_employee_id`)

## 4. Business Rules (Critical for Agents)

- `conversation_type` is required: `live_agent` or `chat_with` (enum — not nullable).
- `status` is `waiting`, `active`, or `closed` (defaults to `waiting` in schema).
- Sample data template: `db/02_data_sample.sql` → one `chat_with` row per tenant (`requester_employee_id` remapped on seed).

## 5. UI Behavior Requirements

Standard flattened CRUD list/view with FK labels via `$GLOBALS['fkMap']`. **Add sample data** only when the tenant table is empty; uses `itm_seed_table_from_database_sql()`.

## 6. API Actions (If Applicable)

N/A — mutations run through `modules/live_chat/api.php`.

## 7. File Structure

- **index.php** — list/create/edit/view/delete handlers
- **list_all.php**, **create.php**, **edit.php**, **view.php**, **delete.php** — wrappers

## 8. Multi-Tenant Rules

All queries scoped by session `company_id`.

## 9. Audit Logging Requirements

`trg_live_chat_conversations_audit_*` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Do not seed without `conversation_type` — fallback random row used to fail with “No fallback value for required column conversation_type.”
- `chat_with` ACL is enforced in `includes/itm_live_chat_support.php`, not in this list module alone.

## 11. Examples of Safe Code Patterns

```php
$stmt = $conn->prepare(
    'SELECT id, conversation_type, status FROM live_chat_conversations WHERE company_id = ? AND deleted_at IS NULL'
);
$stmt->bind_param('i', $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)

Assignee changes notify via `itm_notify_live_chat_conversation_assigned()` (see `modules/live_chat/AGENT_NOTES.md`).
