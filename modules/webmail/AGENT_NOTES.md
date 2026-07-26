# AGENT_NOTES.md - Webmail

## 1. Module Purpose

Session-scoped mailbox UI on the shared **`emails`** send log table. Users see messages where they are recipient (to or cc), sender (from), or own trash. **Email Management** (`modules/emails/`) remains the tenant-wide admin view of the same rows.

## 2. Key Tables

- **emails** — same as Email Management; Webmail filters by `$_SESSION['email']` and `$_SESSION['employee_id']` for trash ownership.
- **webmail_email_reads** — per-employee read state (`company_id`, `employee_id`, `email_id`, `read_at`); private-data exempt (no audit triggers). Existing DBs: `db/migrations/webmail_email_reads.sql`.

## 3. Required Relationships

- **emails** → **companies** (`company_id`).
- **emails.smtp_config_id** → **email_smtp_configurations** (optional).
- **webmail_email_reads** → **emails**, **employees**, **companies** (CASCADE on delete).

## 4. Business Rules (Critical for Agents)

- **Session email:** `$_SESSION['email']` from login / company switch (`work_email`, else `personal_email`). Empty email blocks the mailbox (profile required).
- **Inbox:** `is_deleted = 0`, `is_archived = 0`, `to_email` matches session OR session appears in `cc_email` (comma/semicolon lists).
- **Starred:** `is_deleted = 0`, `is_star = 1`, recipient OR sender scope; **includes** archived starred rows.
- **Sent:** `is_deleted = 0`, `is_archived = 0`, `from_email` matches session.
- **Archived:** `is_deleted = 0`, `is_archived = 1`, recipient OR sender scope.
- **Trash:** `is_deleted = 1` and `deleted_by =` session `employee_id` (personal trash only).
- **Soft delete:** `is_deleted = 1`, `deleted_by`, `deleted_at`, `active = 0`.
- **Hard delete:** `DELETE` only from **Trash** (own rows); confirm in UI.
- **Compose:** `from_email` forced from session; To/CC user-entered; body HTML in `details` via **Quill** WYSIWYG (`js/webmail-compose.js`, Quill 1.3.7 from jsDelivr on `compose.php` only); send via `itm_send_email()` with `log_from_email`, `log_details`, `log_created_by`.
- **Self-sent:** a row can appear in both Inbox and Sent when addresses match both rules.
- **Private data:** no `audit_logs` / triggers on **emails** or **webmail_email_reads** (see root `AGENTS.md`).
- **Read / unread:** stored per employee in **webmail_email_reads**; absence of a row means **Unread**. Opening **view.php** marks read; list/view actions **📩** / **📭** toggle via `delete.php` (`mark_read` / `mark_unread`).

## 5. UI Behavior Requirements

- Folders: Inbox, **Starred**, Sent, Archived, Trash; Compose on separate page.
- Lists: **From**, **To**, and **CC** on every folder tab; **sortable** column headers (▲/▼) on all data columns; pagination (emoji controls), filters (status, starred/archived on inbox, date range, search).
- **Compose body:** Quill Snow editor (bold/italic/underline/strike, headers, lists, link, clear); HTML synced to `body_html` on submit and sanitized server-side with `webmail_render_details_html()`.
- Star / archive / delete actions via POST `delete.php` with CSRF. **Inbox, Starred, Sent, and Archived** use **soft delete** (move to Trash, no browser confirm). **Trash** alone uses **hard delete** with confirm.
- **Read / unread:** list **Read** column; bold row when unread; toggle in Actions; view auto-marks read.
- **View:** read-pane layout (subject header, From/To/CC, date, body pane, folder tabs, toolbar actions); audit fields under collapsible **Technical details**.

## 6. API Actions (If Applicable)

- None; compose uses `itm_send_email()`.

## 7. File Structure

- `index.php` — folder lists.
- `compose.php`, `view.php`, `delete.php`.
- `includes/webmail_helpers.php` — scopes and mutations.
- Wrappers: `create.php` → compose; `edit.php` / `list_all.php` → index.

## 8. Multi-Tenant Rules

- All queries include `company_id = $_SESSION['company_id']`.

## 9. Audit Logging Requirements

- **emails** mutations are not written to `audit_logs` (private-data exempt). View still shows row `created_by` / `updated_by` / soft-delete columns when present.

## 10. Common Pitfalls

- System mail logged with SMTP `from_email` still appears in user Inbox when `to_email` matches session.
- Starred tab must not require `?starred=1` query param (folder sets `is_star = 1` in SQL).
- Do not show other users' trash (`deleted_by` filter).

## 11. Examples of Safe Code Patterns

### List inbox rows

```php
webmail_fetch_list($conn, 'inbox', $company_id, $employee_id, webmail_session_email(), $filters, $perPage, $page);
```

### Send from compose

```php
itm_send_email($to, $subject, $htmlBody, $company_id, [
    'cc_email' => $cc,
    'email_template' => false,
    'log_from_email' => $sessionEmail,
    'log_details' => $htmlBody,
    'log_created_by' => $employee_id,
]);
```

## 12. Module Owner Notes (Optional)

Regression: `php scripts/verify_webmail_module.php` (inbox, starred, sent, trash, archive toggle, delete flows). Sidebar: Planning → Webmail (`includes/ui_config.php`).
