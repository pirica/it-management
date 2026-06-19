# AGENT_NOTES.md - Emails

## 1. Module Purpose
Tenant-scoped email management: send logs, SMTP profiles, and automated alert rules. Default SMTP drives all `itm_send_email()` calls (forgot-password, registration tests, employee onboarding approvals, expiry runners).

## 2. Key Tables
- **emails** — outbound send log (`to_email`, `subject`, `status`, `sent_at`, `details`).
- **email_smtp_configurations** — SMTP host/port/credentials; `is_default = 1` selects the tenant transport. `database.sql` seeds one default **IT Manager** profile per company (`companies` id 1–5).
- **email_alert_rules** — per-company toggles (`rule_slug`, `enabled`, `days_before`, `notify_emails`).

## 3. Required Relationships
- All tables → **companies** (`company_id`, cascade delete).
- **emails.smtp_config_id** → **email_smtp_configurations** (SET NULL on delete).

## 4. Business Rules (Critical for Agents)
- Exactly one active profile per company should have `is_default = 1` (UI clears other defaults on save).
- SMTP passwords stored encrypted via `itm_email_encrypt_password()` / `itm_decrypt()` with server key `itm_smtp_encryption_key()`.
- `itm_send_email($to, $subject, $html, $companyId)` logs every attempt to **emails** when `company_id` resolves.
- Fallback: if no SMTP profile exists, `itm_send_email()` tries Resend (`RESEND_API_KEY` env).
- Alert runner: `php scripts/run_email_alert_rules.php` (schedule via cron). Company 1 seeds include warranty/license rows inside the default 30-day window; other tenants need enabled rules plus `notify_emails`. Use `--verbose` when dispatched count is 0.
- Manual delivery tests: `php scripts/test_email_forgot.php email=… [--company=1]`, `php scripts/test_register_mail.php email=… [--company=1]`.

## 5. UI Behavior Requirements
- Tabs: **Send Logs** | **SMTP Configurations** | **Alert Rules**.
- Stat cards link to filtered send logs (`status=sent` / `failed`).
- SMTP form: toggle **Set as default SMTP**; password field with reveal button; test send on edit.
- Alert rules: per-rule toggle, days-before (expiry rules), comma-separated notify emails.
- Sidebar: **Admin → 📧 Email Management** (`includes/ui_config.php`).

## 6. API Actions (If Applicable)
- No public JSON API; transactional send via `includes/itm_email.php` helpers.
- Regression: `php scripts/verify_emails_module.php`.

## 7. File Structure
- `index.php` — tab shell, POST handlers, stats.
- `tabs/send_logs.php`, `tabs/smtp.php`, `tabs/alert_rules.php`.
- Wrappers `create.php` / `edit.php` redirect to SMTP tab.

## 8. Multi-Tenant Rules
- All queries scoped by `company_id`; `company_id` hidden from UI.

## 9. Audit Logging Requirements
- Triggers: `trg_emails_audit_*`, `trg_email_smtp_configurations_audit_*`, `trg_email_alert_rules_audit_*` in `database.sql`.

## 10. Common Pitfalls
- Saving SMTP without default flag when multiple profiles exist — always confirm `is_default` behaviour.
- Public pages (forgot-password) must pass resolved `company_id` into `itm_send_email()`.
- Onboarding approval emails must use `cr_onboarding_send_approval_email_via_api(..., $companyId)` not MailerLite.

## 11. Examples of Safe Code Patterns

### Send with tenant default SMTP
```php
itm_send_email('user@example.com', 'Subject', '<p>Body</p>', $company_id);
```

### Load default SMTP
```php
$config = itm_email_get_default_smtp_config($conn, $company_id);
```

## 12. Module Owner Notes (Optional)
Integrates with **💰 Budgeting** and **Planning** modules via alert rules (license/warranty expiry) and shared `itm_send_email()` for workflow notifications (e.g. employee onboarding approvals).
