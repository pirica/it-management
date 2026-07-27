# AGENT_NOTES.md - Webmail

## 1. Module Purpose

Session-scoped mailbox UI on the shared **`emails`** send log table. Users see messages where they are recipient (to or cc), sender (from), or own trash. **Email Management** (`modules/emails/`) remains the tenant-wide admin view of the same rows.

## 2. Key Tables

- **emails** — same as Email Management; Webmail filters by `$_SESSION['email']` and `$_SESSION['employee_id']` for trash ownership.
- **webmail_email_reads** — per-employee read state (`company_id`, `employee_id`, `email_id`, `read_at`); private-data exempt (no audit triggers). Existing DBs: `db/migrations/webmail_email_reads.sql`.
- **webmail_signatures** — per-employee compose signatures (`name`, `signature` HTML); scoped by `company_id` + `employee_id`; private-data exempt (no audit triggers). Defined in `db/01_schema.sql` only (no migration file).

## 3. Required Relationships

- **emails** → **companies** (`company_id`).
- **emails.smtp_config_id** → **email_smtp_configurations** (optional).
- **webmail_email_reads** → **emails**, **employees**, **companies** (CASCADE on delete).
- **webmail_signatures** → **companies**, **employees** (CASCADE on delete).

## 4. Business Rules (Critical for Agents)

- **Session email:** `$_SESSION['email']` from login / company switch (`work_email`, else `personal_email`). Empty email blocks the mailbox (profile required).
- **Inbox:** `is_deleted = 0`, `is_archived = 0`, session appears in **To** or **CC** (comma/semicolon lists).
- **Starred:** `is_deleted = 0`, `is_star = 1`, recipient OR sender scope; **includes** archived starred rows.
- **Sent:** `is_deleted = 0`, `is_archived = 0`, `from_email` matches session.
- **Archived:** `is_deleted = 0`, `is_archived = 1`, recipient OR sender scope.
- **Trash:** `is_deleted = 1` and `deleted_by =` session `employee_id` (personal trash only).
- **Soft delete:** `is_deleted = 1`, `deleted_by`, `deleted_at`, `active = 0`.
- **Hard delete:** `DELETE` only from **Trash** (own rows); confirm in UI.
- **Compose:** `from_email` forced from session; **To** and **CC** accept comma- or semicolon-separated addresses (normalized for the send log); body HTML in `details` via **Quill** WYSIWYG (`js/webmail-compose.js`, Quill 1.3.7 from jsDelivr on `compose.php`); send via `itm_send_email()` with `log_from_email`, `log_details`, `log_created_by` (SMTP: one `RCPT TO` per To address). Optional **signature** selected on compose: server merges body + signature HTML via `webmail_compose_merge_body_and_signature()` (both parts sanitized with `webmail_render_details_html()`).
- **Signatures:** per employee only; create/edit/delete via modals on **`signatures.php`** (`js/webmail-signatures.js`, shared markup `includes/webmail_signature_modal.php`). Compose reuses modals for ➕ / delete; POST handlers in `webmail_handle_signature_post()`. Signatures use **hard delete** only.
- **Self-sent:** a row can appear in both Inbox and Sent when addresses match both rules.
- **Private data:** no `audit_logs` / triggers on **emails**, **webmail_email_reads**, or **webmail_signatures** (see root `AGENTS.md`).
- **Read / unread:** stored per employee in **webmail_email_reads**; absence of a row means **Unread**. Opening **view.php** marks read; list/view actions **📩** / **📭** toggle via `delete.php` (`mark_read` / `mark_unread`).

## 5. UI Behavior Requirements

- Folders: Inbox, **Starred**, Sent, Archived, Trash; **Signatures** tab (`signatures.php`); Compose on separate page.
- Folder tabs + Signatures + Compose rendered via `webmail_render_tabs()` in `includes/webmail_helpers.php`.
- Lists: **From**, **To**, and **CC** on every folder tab; **sortable** column headers (▲/▼) on all data columns; **From / To / CC / Subject** link to **view.php** with plain (undecorated) styling; pagination (emoji controls), filters (status, starred/archived on inbox, date range, search).
- **List header ➕:** `href="create.php"` (redirects to **compose.php**) with canonical `title="Create"`; gated by Settings `new_button_position` (`itm_resolve_new_button_position`) in left/right slots.
- **Bulk delete:** when row count ≥ `records_per_page`, standard `bulk-delete-form` (Select to Delete / Clear Table); non-Trash folders soft-delete to Trash, Trash permanently deletes; scoped to current folder + filters via `delete.php` `bulk_action`.
- **Compose layout:** To, From, CC, Subject, Select Signature, and Body on inline label rows (`.webmail-compose-row`).
- **Compose body:** Quill Snow editor (bold/italic/underline/strike, headers, lists, link, clear); HTML synced to `body_html` on submit and sanitized server-side with `webmail_render_details_html()`.
- **Signatures UI:** list on `signatures.php`; create/edit in modal (Name + Quill); delete in confirm modal; compose dropdown with empty option, signature names, ➕, **✏️** (edit selected), and **🗑️** (delete). Signature modal Quill teardown resets `.webmail-quill-wrap` so Snow toolbars are not duplicated on reopen. After edit from compose (`return_to=compose`), redirect keeps the same `signature_id` selected in the dropdown. List table uses `data-itm-no-table-actions-layout="1"` so Settings table-actions layout does not clone action cells; `js/webmail-signatures.js` uses document-level click delegation for edit/delete/create.
- Star / archive / delete actions via POST `delete.php` with CSRF. **Inbox, Starred, Sent, and Archived** use **soft delete** (move to Trash, no browser confirm). **Trash** alone uses **hard delete** with confirm.
- **Read / unread:** list **Read** column; bold row when unread; toggle in Actions; view auto-marks read.
- **View:** read-pane layout (subject header, From/To/CC, date, body pane, folder tabs, toolbar actions); **📄** Export PDF (print dialog via `window.itmExportViewAsPdf()` + hidden summary table); temporary **📱 / WhatsApp / 📨** share on `view.php` (`join.php`, `itm_crud_record_share_create_webmail()` — enable **webmail** in Share Modules per company); audit fields under collapsible **Technical details**.
- **List table:** `data-itm-no-import-excel="1"` plus export opt-outs — no table-tools Import Excel (mailbox is not a CRUD import surface).

## 6. API Actions (If Applicable)

- None; compose uses `itm_send_email()`.

## 7. File Structure

- `index.php` — folder lists.
- `compose.php`, `signatures.php`, `view.php`, `delete.php`, `join.php` — temporary message share (QR / code).
- `includes/webmail_helpers.php` — scopes, mutations, signatures, tabs.
- `includes/webmail_signature_modal.php` — shared signature modals.
- Wrappers: `create.php` → compose; `edit.php` / `list_all.php` → index.
- **UI configuration coverage:** gate-excluded non-standard CRUD (`scripts/data/ui_configuration_excluded_modules.txt`, `docs/list_bespoke_UI.txt`) — checks print `[n/a][pass|fail|n/a]`; **Back & Save** on create/edit stubs reviewed in `scripts/data/ui_configuration_reviewed.json`.

## 8. Multi-Tenant Rules

- All queries include `company_id = $_SESSION['company_id']`.
- Signatures additionally require `employee_id = $_SESSION['employee_id']`.

## 9. Audit Logging Requirements

- **emails** mutations are not written to `audit_logs` (private-data exempt). View still shows row `created_by` / `updated_by` / soft-delete columns when present.
- **webmail_signatures** — no audit triggers or `audit_logs` rows.

## 10. Common Pitfalls

- System mail logged with SMTP `from_email` still appears in user Inbox when `to_email` matches session.
- Starred tab must not require `?starred=1` query param (folder sets `is_star = 1` in SQL).
- Do not show other users' trash (`deleted_by` filter).
- **view.php toolbar icons:** use `itm_ui_action_emoji()` and PHP `\u{…}` escapes for webmail-only glyphs—do not paste emoji literals after a non–UTF-8 file restore (mojibake in buttons).
- Signature merge on send is **server-side**; client body must not be trusted to include the signature.

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

### Signature CRUD

```php
webmail_signatures_list($conn, $company_id, $employee_id);
webmail_signature_create($conn, $company_id, $employee_id, $name, $html);
webmail_compose_merge_body_and_signature($bodyHtml, $signatureHtml);
```

## 12. Module Owner Notes (Optional)

Regression: `php scripts/verify_webmail_module.php` (inbox, starred, sent, trash, archive toggle, delete flows, signatures CRUD + merge, message share payload helpers). Share matrix: enable **webmail** under Share Modules. Sidebar: Planning → Webmail (`includes/ui_config.php`).
