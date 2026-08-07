# Live Chat Subsystem

Real-time messaging system built with procedural PHP and polled JSON APIs. Features dual communication flows (Live Agent IT support and private Chat with peer messaging), automatic ticket SLA/response tracking, custom closure ratings, and in-app notifications.

| Aspect | Detail |
|-------|--------|
| **Registry Slug** | `live_chat` (System module; enabled/disabled per-company via Company Module Access) |
| **Security / Audit** | Message contents (`live_chat_messages`) and ephemeral typing indicators (`live_chat_typing`) are private-data exempt from audit triggers and `itm_log_audit()` hooks. Conversation metadata is auditable. |
| **SLA Tracking** | Live Agent conversations automatically apply SLA target response/resolve policies on creation, and first support reply stamps `first_response_at`. |
| **Storage Maps** | Live Agent logs to ticket folders in `tickets_photos/`; Peer chats log to user private workspaces in `files/{company_id}/Private/{username}_{employee_id}/Live-Chat/`. |

---

## 1. Dual Communication Flows

The Live Chat subsystem is split into two distinct functional channels:

### A. Live Agent IT Support (`conversation_type = 'live_agent'`)
Tied directly to the helpdesk ticketing workflow.
- **Entry:** Employees launch a Live Agent session to request support. This can target a new ticket, an existing open ticket, or reopen a recently closed ticket.
- **Visibility:** Support agents (defined as employees assigned to the IT department via `itm_live_chat_is_support_agent()`) can view all company Live Agent conversations. Regular employees see only their own support sessions.
- **Assignee:** A support agent can "claim" an open conversation (`claim_conversation` action), which assigns both the conversation and the underlying ticket to them.

### B. Chat with Colleague (`conversation_type = 'chat_with'`)
Private peer-to-peer messaging between employees.
- **Entry:** Initiated from the contacts list, org chart, or search.
- **Visibility:** Highly private. Only the explicit participants registered in the `live_chat_participants` table may view or send messages in the thread. IT support agents or administrators have **no visibility** into Chat with threads unless they are an active participant. Enforced globally by `itm_live_chat_can_view_conversation()`.
- **Tenant Scope:** Scoped strictly to the active `company_id`. Peer listing and creation are controlled by `it_settings.chat_same_tenant` (Settings → All roles):
  - **Enabled (1):** Colleague options are restricted to employees whose home company matches the active `company_id`.
  - **Disabled (0):** Colleague options include employees from any company the signed-in user has cross-tenant access to (via `itm_list_employee_accessible_companies()`).

---

## 2. Key Tables and Database Relations

The subsystem relies on five dedicated database tables:

| Table | Primary Role | Audit/Private Status |
|-------|--------------|----------------------|
| **`live_chat_conversations`** | Conversation header (type, ticket link, assignee, status, rating, closure notes) | **Audited** (standard INSERT/UPDATE/DELETE triggers) |
| **`live_chat_participants`** | Junction table mapping `employee_id` to conversations (handles ACL boundaries) | **Audited** (standard triggers) |
| **`live_chat_messages`** | Contains plaintext message bodies, attachment names, and sender stamps | **Exempt** (no triggers, no audit trail to preserve privacy) |
| **`live_chat_typing`** | Ephemeral typing presence indicators containing expirations | **Exempt** (typing rows hard-deleted on expiry) |
| **`employee_notifications`** | In-app notification queue for incoming messages and alerts | **Audited** (standard triggers) |

### Database Constraints and Cascades
- `live_chat_conversations.ticket_id` references `tickets.id` (NULL allowed for peer chats; SET NULL on delete).
- `live_chat_conversations.requester_employee_id` and `assigned_to_employee_id` reference `employees.id` (RESTRICT on delete).
- `live_chat_participants` and `live_chat_messages` are linked to `live_chat_conversations` via cascading foreign keys (`ON DELETE CASCADE`), ensuring automated garbage collection upon conversation deletion.

---

## 3. Workflows & SLA Policies

### A. SLA Policy Application
On conversation/ticket creation via `start_live_agent`:
1. The system calls `itm_ticket_sla_apply_on_create()`, which matches the ticket priority against standard minutes in `ticket_sla_policies`.
2. This stamps `sla_response_due_at` and `sla_resolve_due_at` on the `tickets` record.
3. Upon the first reply from any support agent (non-requester):
   - `first_response_at` is stamped as `NOW()`.
   - The system checks if `first_response_at` is less than or equal to `sla_response_due_at` to mark response SLA compliance.

### B. Closing & Rating
1. A support agent or the requester closes the conversation (`close_conversation` action).
2. This updates `live_chat_conversations.status` to `closed` and sets `tickets.resolved_at` if linked.
3. The requester is prompted to supply a rating (TINYINT 1–5 stars) and feedback.
4. Ratings are saved to `live_chat_conversations.rating` and `live_chat_conversations.rating_notes`.

### C. In-App Notifications
- Sending a message checks for active participants. Any participant not currently viewing the conversation receives a row in `employee_notifications`.
- These appear as dynamic badges in the app header and can be marked read via `mark_notification_read`.

---

## 4. Storage Architecture

Attachments and chat history logs are structured dynamically based on conversation type:

### A. Live Agent Storage
- Files and structural backups reside within the ticket photo directory:
  - Path: `tickets_photos/{ticket_id}/` (hardened under the `upload` policy).
  - Conversation logs: Written to `tickets_photos/{ticket_id}/chat.json`.
  - Attachments: Uploaded and renamed securely within the same folder.

### B. Peer Chat Storage
- Files and logs are stored inside the sender's private employee folder:
  - Path: `files/{company_id}/Private/{username}_{employee_id}/Live-Chat/{id}_chat_{datetime}/` (hardened under the `deny_http` policy).
  - Proxy serving: Served through `modules/explorer/file.php` since direct HTTP access is blocked.

---

## 5. UI Layout and Client Integration

The Live Chat module (`modules/live_chat/index.php`) features a responsive tri-pane layout:

```
+--------------------------------------------------------------+
| [Standard App Sidebar]                                       |
+------------------------------------+-------------------------+
| [Conversations List Pane]          | [Chat Workspace Pane]   |
| - Filter tabs (Active/Closed)      | - Conversation Header   |
| - Conversation cards (badges,      | - Message History area  |
|   typing indicator, active status) | - File attachments bar  |
| - "➕ Launch" toolbar options      | - Message input + 💾    |
+------------------------------------+-------------------------+
```

### Launch Option Modals
Upon launching a new chat flow, the employee is presented with configurable landing options:

| Flow | Launch Cards | Destinations / Helpers |
|------|--------------|------------------------|
| **Live Agent** | Start Live Chat, Appointment, Knowledge Base, Create Ticket, Re-open Ticket, Email IT | In-app chat, `modules/appointments/`, `modules/knowledge_base/`, `modules/tickets/`, `send-email.php` |
| **Chat with Colleague** | Message Colleague, Knowledge Base, Company Contacts, Org Chart | In-app chat picker, `modules/knowledge_base/`, `modules/contacts/`, `modules/org_chart/` |

---

## 6. Public API Interfaces (`modules/live_chat/api.php`)

All operations utilize an AJAX JSON router. Every endpoint enforces rate limiting via `itm_api_enforce_rate_limit_or_exit($conn)`.

### Principal Actions:
- `list_conversations` (GET): Fetches conversation headers matching the user's role-level access rules.
- `get_conversation` (GET): Retrieves conversation details + participants (`id` required).
- `get_messages` (GET): Retrieves message records for the active thread.
- `send_message` (POST): Validates CSRF, sanitizes text, and inserts `live_chat_messages` rows.
- `upload_attachment` (POST): Accepts file binaries, validates MIME limits, and writes to storage.
- `set_typing` (POST): Upserts typing indicators in `live_chat_typing` with a rolling 5-second expiration.
- `poll` (GET): Compact real-time state aggregator (returns messages delta, typing states, and notification triggers).
- `start_live_agent` (POST): Provisions conversations, associates or spawns a ticket, and sets SLA timers.
- `start_chat_with` (POST): Asserts tenant constraints, registers participants, and starts a peer thread.
- `claim_conversation` (POST): Assigns conversation and ticket to the claiming support agent.
- `close_conversation` (POST): Terminates real-time thread state and stamps ticket resolution.

---

## 7. Developer Setup & Troubleshooting

### Local Environment Verification
To run the automated regression tests and verify the complete Live Chat schema, SLA triggers, ACL boundaries, notification flows, and attachment paths:

```bash
# Standard CLI validation
php scripts/verify_live_chat.php

# On Windows Dunebox/Laragon (using absolute PHP 7.4.33 path):
& "D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/verify_live_chat.php
```

### Common Developer Pitfalls
1. **Direct HTTP Attachment Links:** Direct references to `../../files/…` will break because of the `deny_http` `.htaccess` policy. Always construct links using `itm_files_serve_url($relativePath)` which delegates serving to the `modules/explorer/file.php` proxy.
2. **Moisture / Stale Data Logs:** Ensure that `verify_live_chat.php` is executed on a clean database clone if other tests have polluted the active tenant workspace.
3. **No-Audit Auditing:** Never add triggers or PHP `itm_log_audit()` calls for the `live_chat_messages` or `live_chat_typing` tables. Doing so violates PII confidentiality rules and will fail CI check scripts.
