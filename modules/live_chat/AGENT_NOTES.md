# AGENT_NOTES.md - Live Chat

## 1. Module Purpose

Real-time messaging with two entry flows:

- **Live Agent** (`conversation_type = live_agent`) — IT support queue tied to a ticket; support agents see all company conversations; employees see their own.
- **Chat with** (`conversation_type = chat_with`) — peer messaging between employees **homed in the active `company_id` only** (not `employee_companies` cross-grant visitors); only participants can view the thread (IT cannot see unless they are a participant).

The floating knowledge-base chatbot (`js/chatbot.js`, `enable_chatbot`) is **not** replaced by this module.

## 2. Key Tables

- **live_chat_conversations** — conversation header (type, ticket link, assignee, status, rating, storage path)
- **live_chat_participants** — who may read/send in a thread
- **live_chat_messages** — message body + attachment metadata (private-data exempt from audit triggers)
- **live_chat_typing** — ephemeral typing indicators (no audit triggers)
- **ticket_activity** — ticket timeline events (e.g. live chat started)
- **ticket_comments** — ticket comments (internal notes for support agents)
- **ticket_sla_policies** — per-priority response/resolve SLA minutes
- **employee_notifications** — in-app inbox rows for chat events

**tickets** (extended): `first_response_at`, `resolved_at`, `sla_response_due_at`, `sla_resolve_due_at`

## 3. Required Relationships

- **live_chat_conversations** → `tickets` (`ticket_id`, optional for `chat_with`)
- **live_chat_conversations** → `employees` (`requester_employee_id`, `assigned_to_employee_id`)
- **live_chat_participants** → `live_chat_conversations` (CASCADE delete)
- **live_chat_messages** → `live_chat_conversations` (CASCADE delete)
- **ticket_sla_policies** → `ticket_priorities` (`priority_id`)
- **ticket_activity** / **ticket_comments** → `tickets`

## 4. Business Rules (Critical for Agents)

- All queries scoped by `company_id`.
- **Chat with** peers: `it_settings.chat_same_tenant` (default **1**, toggled in **Settings → All roles**). When **1**, `itm_live_chat_peer_eligible_for_company()` requires home `employees.company_id`; when **0**, `employee_companies` grants may appear in the peer list (`itm_user_options_for_company()`).
- **Live Agent** requires a ticket (existing or created via `start_live_agent`).
- **Chat with** has no ticket; ACL is participant-only — `itm_live_chat_can_view_conversation()` enforces this.
- Support agents: employees in the IT department (`itm_live_chat_is_support_agent()`).
- **live_chat_messages** must not get audit triggers or `itm_log_audit()` — private message content.
- Rating: `rating` TINYINT 1–5 on conversation close.
- Storage:
  - Live Agent: `tickets_photos/{ticket_id}/chat.json` + attachments
  - Chat with: `files/{company_id}/Private/{username}_{employee_id}/Live-Chat/{id}_chat_{datetime}/`
- SLA: `itm_ticket_sla_apply_on_create()` stamps due dates; first agent message sets `first_response_at`.

## 5. UI Behavior Requirements

Tri-pane layout (`index.php` + `css/live_chat.css` + `js/live_chat.js`):

1. App sidebar (standard)
2. Conversation list sidebar
3. Chat panel + employee details panel

Landing buttons: **Live Agent** and **Chat with**, each opening launch options (`in_app`, `browser_tab`, `browser_window`).

Default launch options:

| Flow | Options |
|------|---------|
| Live Agent | Start live chat, Knowledge Base, List all (knowledge-base), Create ticket, Re-open ticket, Email IT |
| Chat with | Message colleague, List all (knowledge-base), Company contacts, Org chart |

Polling via `api.php?action=poll` (not WebSockets). CSRF on all mutating POSTs.

## 6. API (`modules/live_chat/api.php`)

Actions: `list_conversations`, `get_conversation`, `get_messages`, `send_message`, `upload_attachment`, `delete_attachment`, `set_typing`, `poll`, `start_live_agent` (`ticket_mode`: `new`, `existing`, `reopen`), `start_chat_with`, `claim_conversation`, `rate_conversation`, `close_conversation`, `list_notifications`, `mark_notification_read`, `list_open_tickets`, `list_closed_tickets`, `list_employees`, launch-option helpers.

Rate limit: `itm_api_enforce_rate_limit_or_exit($conn)` on every request.

## 7. File Structure

- `index.php` — tri-pane shell
- `api.php` — JSON AJAX router
- Helpers: `includes/itm_live_chat_*.php`, `includes/itm_ticket_*.php`, `includes/itm_employee_notifications.php`

## 8. Integration

- `modules/tickets/view.php` — SLA fields in detail view; comments list + add form
- `includes/ui_config.php` — sidebar entry 💬 Live Chat
- `js/chatbot.js` — widget shortcuts: Live Agent, Chat with, List all (knowledge-base)
- `db/02_data.sql` — `modules_registry` row; `ticket_sla_policies` seeds + cross-company replication

## 9. Regression

```bash
php scripts/verify_live_chat.php
```

Run when changing ACL, storage, SLA, API actions, or schema.

## 10. Migration

Existing databases: `db/migrations/live_chat.sql` (DROP + CREATE new tables; commented `ALTER` notes for `tickets` SLA columns). Triggers ship in `db/03_triggers.sql`.

## 12. Module Owner Notes

Company module access: enable `live_chat` in Company Module Access matrix for non-admin tenants. Registry slug: `live_chat`.
