# AGENT_NOTES.md - Emails

## 1. Module Purpose
Tenant-scoped email management: send logs, SMTP profiles, and automated alert rules. Default SMTP drives all `itm_send_email()` calls (forgot-password, registration tests, employee onboarding approvals, expiry runners).

## 2. Key Tables
- **emails** — outbound send log (`to_email`, `subject`, `status`, `sent_at`, `details`).
- **email_smtp_configurations** — SMTP host/port/credentials plus IMAP port (default 143) and POP3 port (default 110), TLS mode (default `None`), and require-secure toggle (default off); `is_default = 1` selects the tenant transport. `database.sql` seeds one default **IT Manager** profile per company (`companies` id 1–5).
- **email_alert_rules** — per-company toggles (`rule_slug`, `enabled`, `days_before`, `notify_emails`).

## 3. Required Relationships
- All tables → **companies** (`company_id`, cascade delete).
- **emails.smtp_config_id** → **email_smtp_configurations** (SET NULL on delete).

## 4. Business Rules (Critical for Agents)
- Exactly one active profile per company should have `is_default = 1` (UI clears other defaults on save).
- SMTP passwords stored encrypted via `itm_email_encrypt_password()` / `itm_decrypt()` with server key `itm_smtp_encryption_key()`.
- `itm_send_email($to, $subject, $html, $companyId)` logs every attempt to **emails** when `company_id` resolves.
- Fragment HTML bodies are auto-wrapped in the login-style transactional template (`itm_email_build_transactional_html()`). Pass `email_template` (array with `subtitle`, `button_text`, `button_url`, `footer_text`) or `email_template => false` to skip wrapping.
- Fallback: if no SMTP profile exists, `itm_send_email()` tries Resend (`RESEND_API_KEY` env).
- Alert runner: `php scripts/run_email_alert_rules.php` (schedule via cron). Company 1 `database.sql` seeds use relative warranty/license expiry (`DATE_ADD(CURDATE(), …)`) so rows stay inside the default 30-day window after import. `verify_emails_module.php` **fails** (does not skip) when the window is empty; it inserts a disposable company-1 license sample, re-asserts, then deletes it. Other tenants need enabled rules plus `notify_emails`. Use `--verbose` when dispatched count is 0.
- Manual delivery tests: `php scripts/test_email_forgot.php email=… [--company=1]`, `php scripts/test_register_mail.php email=… [--company=1]`. Forgot-password emails include a **Reset password** CTA button plus the full reset URL in the body for copy/paste. The forgot test script stores a **real 24-hour reset token** for the matching employee (not a fixed placeholder).

## 5. UI Behavior Requirements
- **View audit meta:** Detail view renders all six scaffold audit columns via `itm_crud_render_view_audit_meta_rows()` / `itm_crud_render_audit_cell_value()` (`*_by` employee names, `*_at` as `d-m-Y - H:i:s`). Row meta is for soft-delete display only; this module stays **private-data exempt** from `audit_logs` triggers.
- Tabs: **Send Logs** | **SMTP Configurations** | **Alert Rules**.
- Validation errors on `index.php` use `itm_render_alert_errors($errors)` (not raw `foreach` alert markup).
- Stat cards link to filtered send logs (`status=sent` / `failed`); search term preserved on stat-card links when active.
- **List header:** centered `h1` echoes `sanitize($moduleListHeading)` from `itm_sidebar_label_for_module()` inside `data-itm-new-button-managed="server"` (Settings emoji/icon overrides); SMTP tab ➕ create respects `new_button_position`.
- **Send Logs tab:** server-side search via `$_GET['search']` on `to_email`, `subject`, `status`, `details`, and `sent_at` (SQL `LIKE`); Search submit + emoji-only 🔙 reset on `tabs/send_logs.php` (preserves `sort` / `dir`). Column sort (`to_email`, `subject`, `status`, `sent_at`, `details`) via `sort` + `dir` with ▲/▼ headers. Pagination uses `itm_resolve_records_per_page()` with `LIMIT`/`OFFSET`, filtered row count, and **Previous** / **Next** controls preserving `tab`, `status`, `search`, `sort`, `dir`, and `page`. Bulk toolbar (`Select to Delete`, `Cancel`, `Clear Table`) visible when row count ≥ `records_per_page`; soft-delete via `delete.php` (private-data exempt — no `audit_logs`). **UI configuration audit:** gate-excluded (`ui_configuration_excluded_modules.txt`); intentional `[n/a][pass|fail|n/a]` lines marked `[reviewed]` in `scripts/data/ui_configuration_reviewed.json` because the list table lives in `tabs/send_logs.php`, not `index.php`.
- SMTP form: toggle **Set as default SMTP**; password field with reveal button; **IMAP** port; **POP3** port, TLS mode, and require-secure toggle; test send on edit.
- Alert rules: per-rule toggle, days-before (expiry rules), comma-separated notify emails.
- Sidebar: **Admin → 📧 Email Management** (`includes/ui_config.php`).

## 6. API Actions (If Applicable)
- No public JSON API; transactional send via `includes/itm_email.php` helpers.
- Regression: `php scripts/verify_emails_module.php`.

## 7. File Structure
- `index.php` — tab shell, POST handlers, stats.
- `tabs/send_logs.php`, `tabs/smtp.php`, `tabs/alert_rules.php`.
- `delete.php` — send-log soft-delete (bulk / clear table).
- Wrappers `create.php` / `edit.php` redirect to SMTP tab.

## 8. Multi-Tenant Rules
- All queries scoped by `company_id`; `company_id` hidden from UI.

## 9. Audit Logging Requirements
- **Send log (`emails`):** private-data exempt — no `trg_emails_audit_*` triggers and no `audit_logs` rows for send-log mutations (see `AGENTS.md` → **Private data — no audit trail**).
- **SMTP / alert rules:** `email_smtp_configurations` and `email_alert_rules` remain auditable via `trg_*_audit_*` triggers in `database.sql`.

## 10. Common Pitfalls
- Saving SMTP without default flag when multiple profiles exist — always confirm `is_default` behaviour. [Cursor-Valid]
- Public pages (forgot-password) must pass resolved `company_id` into `itm_send_email()`. [Cursor-Valid]
- Onboarding approval emails must use `cr_onboarding_send_approval_email_via_api(..., $companyId)` not MailerLite. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Send with tenant default SMTP
```php
itm_send_email('user@example.com', 'Subject', '<p>Body</p>', $company_id, [
    'email_template' => [
        'subtitle' => 'Optional headline',
        'button_text' => 'Open',
        'button_url' => BASE_URL . 'login.php',
    ],
]);
```

### Load default SMTP
```php
$config = itm_email_get_default_smtp_config($conn, $company_id);
```

## 12. Module Owner Notes (Optional)
Integrates with **💰 Budgeting** and **Planning** modules via alert rules (license/warranty expiry) and shared `itm_send_email()` for workflow notifications (e.g. employee onboarding approvals).
