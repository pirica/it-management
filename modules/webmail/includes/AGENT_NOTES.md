# AGENT_NOTES.md - Webmail Includes

## 1. Module Purpose

Shared PHP partials and helpers for `modules/webmail/` — mailbox scopes, mutations, signatures, tabs, and compose/signatures modals. Parent module: `modules/webmail/AGENT_NOTES.md`.

## 7. File Structure

| File | Role |
|------|------|
| `webmail_helpers.php` | Folder scopes (Inbox/Starred/Sent/Archived/Trash), read/unread (`webmail_email_reads`), soft/hard delete, signature CRUD handlers, `webmail_render_tabs()`, compose body merge/sanitize |
| `webmail_signature_modal.php` | Create/edit signature modal markup (Quill) — used by `signatures.php` and compose |
| `webmail_compose_preview_modal.php` | Compose **🔎** preview modal; fed by `compose.php?ajax_action=preview_message` |

## 4. Business Rules (Critical for Agents)

- All list scopes filter `emails` by session `email` / `employee_id` — never cross-employee trash or reads.
- **Private data:** `emails`, `webmail_email_reads`, `webmail_signatures` exempt from audit triggers.
- Compose HTML sanitized via `webmail_render_details_html()`; signatures merged with `webmail_compose_merge_body_and_signature()`.

## 10. Common Pitfalls

- Changing folder rules requires updating both `webmail_helpers.php` and list queries in `index.php`.
- Signature modal Quill teardown must reset `.webmail-quill-wrap` to avoid duplicate toolbars (`js/webmail-signatures.js`).
