# AGENT_NOTES.md - Emails Tabs

## 1. Module Purpose
Hosts the individual PHP view files that render the tab panes (Send Logs, SMTP configurations, and Alert Rules) within the parent Emails module (`modules/emails/index.php`).

## 2. Key Tables
- **emails** — outbound send logs displayed on the Send Logs tab.
- **email_smtp_configurations** — SMTP server profiles managed on the SMTP tab.
- **email_alert_rules** — alert triggers managed on the Alert Rules tab.

## 3. Required Relationships
- **emails** → links to **email_smtp_configurations** via `smtp_config_id`.
- All tables belong to **companies** (`company_id`).

## 4. Business Rules (Critical for Agents)
- **Inherited Context**: These tab files are included dynamically within `modules/emails/index.php`. They must never re-initialize database connections (`$conn`) or override the established session company scoping variables.
- **Validation and Save Handlers**: Save, toggle, and SMTP test send actions are processed in the parent `index.php` controller before tabs render.

## 5. UI Behavior Requirements
- **alert_rules.php** — Configures automatic notifications (expiry alerts, reminders) with toggles and target recipients.
- **send_logs.php** — Lists historical emails with status indicators (Sent/Failed). Shows detail popups on click.
- **smtp.php** — Renders the form to add/edit SMTP endpoints, including host, port, username, encrypted password, from address/name, and secure toggles.
- Action column cells use `class="itm-actions-cell"` and `data-itm-actions-origin="1"`.

## 6. API Actions (If Applicable)
- None (parent `index.php` handles all POST/API interactions).

## 7. File Structure
- **alert_rules.php** — Alert rules list and form fields.
- **send_logs.php** — Sent logs table and filter controls.
- **smtp.php** — SMTP profile editor panel.
- **index.html** — Directory listing prevention placeholder.

## 8. Multi-Tenant Rules
- Data shown on all tabs is strictly filtered by the parent session's `$company_id`.

## 9. Audit Logging Requirements
- State changes (like editing alert rules or SMTP configurations) trigger unconditional database audit logging.

## 10. Common Pitfalls
- Hand-coding independent database queries inside tab partials that forget to filter by the tenant's `company_id`.
