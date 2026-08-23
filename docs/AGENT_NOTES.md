# AGENT_NOTES.md - docs

## 1. Module Purpose
Canonical documentation that is not source code: upload maps, README assets, module inventory lists for agents.

## 7. File Structure
- **docs/VAULT.md** — vault/master-key workflows (create, unlock, change, forgotten-key remediation), optional TOTP 2FA, client-side key generation, one-time display after **💾** save (and on **🔑** generate), and `itm_send_email()` notification contract (no secrets in mail).
- **docs/ROLES_PERMISSIONS.md** — Role-Based Access Control (RBAC) permission matrix, role hierarchy, active employee counts, and AJAX api actions.
- **docs/security_assessment_report.md** — a comprehensive ethical and defensive security assessment report of the repository detailing reconnaissance, vulnerabilities, and hardening recommendations.
- **docs/EMAIL_MANAGEMENT.md** — multi-tenant email management system, SMTP/IMAP/POP3 configuration, Send Logs, transactional templates, and automated alert rules.
- **docs/CRUD_RECORD_SHARE.md** — temporary QR/6-digit share for CRUD record modules (`includes/itm_crud_record_share.php`): capable slug list, UI locations, wiring contract, seeds, regression commands.
- **docs/EXPLORER.md** — multi-tenant secure filesystem with trash management, API traversal boundary protection, `.htaccess` upload hardening with `deny_http` policies, empty `index.html` placeholders, and vault integration.
- **docs/CHATBOT.md** — floating assistant chatbot, multi-tenant Knowledge Base, security guards (XSS escaping, CSRF tokens, rate limits), and IT escalation keywords.
- **docs/PRIVATE_CONTACTS.md** — secure address book utilizing AES-256 PII encryption-at-rest, PHP-side search, sort, and pagination, temporary sharing, and settings list Excel import/export integration.
- **docs/OPS_REPORT.md** — daily operations report, automatic templates, D-2 lockout rules for standard users, dynamic UI label mapping stored in JSON, and cascaded auditable deletes.
- **docs/REQUEST_PASSWORD.md** — multi-stage human approval chain for workstation/system password resets, visual signature card tracking, and creator-only soft-delete restrictions.
- **docs/BACKUP_TAPE_LOG.md** — monthly server backup verification grid, derived day names, Sunday highlights, historical immutability locks, and role-based field restrictions.
- **docs/APPOINTMENTS.md** — employee self-service IT appointment scheduling, business hours modalities, week-grid slots, multi-concurrency unique locks, and API booking paths.
- **docs/PRODUCT_GAPS_AND_MISSING.md** — living Phase 2 backlog: partial vs missing features from the product/architecture proposal; appointment pack gaps (APPT-* / APSET-* IDs); reconciliation with stale `FEATURE_ROADMAP.md` rows.
- **docs/COMPANY_MODULE_ACCESS.md** — admin-only multi-tenant module enablement, opt-out access rules, global registry catalogs, auto-registration self-discovery, and matrix AJAX settings.
- **docs/LICENSE_MANAGEMENT.md** — software license tracking, lookup dependencies, localized dates and normalized price formatting, soft-delete audit meta details, and import/export layouts.
- **docs/LIVE_CHAT.md** — Real-time messaging subsystem with Live Agent and Chat-with support, ticket/SLA integration, secure non-audited messaging, ephemeral typing presence, and local file/photo storage mapping.
- **docs/TICKET_SLA_DASHBOARD.md** — SLA Command Center dashboard (`modules/ticket_sla_dashboard/`), breach columns on `tickets`, cron monitor, badges on ticket list/view, JSON API summary/list.
- **docs/TICKET_PRODUCTIVITY.md** — canned responses CRUD, ticket comment picker (Shift+F2), merge (`merged_into_ticket_id`), public CSAT (`ticket-csat.php`), migrations, and `verify_ticket_productivity.php`.
- **docs/NOTIFICATIONS.md** — in-app notification center: `employee_notifications` table, `itm_notify_employee()` emitters, header bell API/JS, digest email runner.
- **docs/APPROVAL_INBOX.md** — unified approval inbox: `approval_inbox_items` table, adapter sync from `request_password`, `employee_onboarding_requests`, `approvals`, and `forecast_revisions`, header pending badge, `includes/itm_approval_inbox.php` helpers, inbox UI decide proxy, regression script.
- **docs/ORG_CHART.md** — interactive hierarchical org chart from self-referential reporting lines, recursive cycle loops detection, dynamic drag-and-drop AJAX persistence, and responsive layouts.
- **docs/SYSTEM_STATUS.md** — Admin-only server diagnostics dashboard covering CPU/RAM/disk metrics, native Linux proc reporting vs Windows PowerShell fallbacks, and real-time SQL/on-disk storage caching.
- **docs/BOOKING.md** — guest-facing `booking/` portal: four-step checkout, manage reservation (lookup, cancel, change contacts), ITM Hospitality admin modules, schema, local URLs, and review of strengths/gaps.
- **docs/QR.md** — employee-scoped `modules/qr/` QR Generator: types, static/dynamic encoding, public `r.php` / `asset.php`, regression script.
- **docs/HOTEL_BOOKING_DISTRIBUTION.md** — partner distribution API (`modules/hotel_booking_api/`) with JSON/OpenTravel XML/Booking.com/OHIP wire adapters, channel admin, ARI push/pull, and reservation book/modify/cancel.
- **docs/API_V2.md** — partner JSON REST gateway (`modules/api_v2/router.php`): paid-tier scoped `X-API-Key`, PATH_INFO routes, OpenAPI 3.0 spec (`scripts/openapi.php`), tickets/equipment MVP handlers.
- **docs/STRIPE_CHECKOUT.md** — guest portal Stripe Checkout (`includes/itm_stripe_checkout.php`, `booking/payment-stripe.php`, `booking/stripe-webhook.php`, `hotel_booking_payment_events`).
- **docs/SSO_LDAP.md** — LDAP SSO login (v1): `companies.sso_*` encrypted config, `companies.sso_jit_enabled` JIT provisioning, `employees.sso_subject`, `sso-ldap.php` entry, `includes/itm_ldap_auth.php` helpers, Companies edit admin UI, PHP **`ldap`** extension (Apache SAPI), regression `php scripts/verify_sso_ldap.php`.
- **docs/SSO_SAML.md** — SAML 2.0 SSO: `sso-saml.php` / `sso-saml-acs.php`, `includes/itm_saml_auth.php`, company provider select, ACS URL, attribute mapping.
- **docs/INTEGRATION_WEBHOOKS.md** — outbound integration webhooks: `integration_webhooks` / `integration_webhook_deliveries`, event types (`ticket.created`, `ticket.status_changed`, `ticket.priority_changed`, `ticket.comment_created`, `expense.approved`, `equipment.disposed`, …), `includes/itm_webhook_queue.php`, delivery runner, verify script.
- **docs/ASSET_LIFECYCLE.md** — equipment lifecycle stages, depreciation helpers, `equipment_lifecycle_events`, Reports Hub lifecycle chart, `verify_asset_depreciation.php`.
- **docs/database_fixed/** — notes that the cancellation-policy RCE remediation needed no DB schema change (`AGENT_NOTES.md`); live fix is in `includes/itm_hotel_booking.php` + `booking/cancellation_policy/.htaccess`.
- **docs/FEATURE_ROADMAP.md** — product and technical feature roadmap: gaps, 10 proposed features (SLA dashboard, approval inbox, automation, scheduled reports, Stripe, ticket productivity, asset lifecycle, SSO, webhooks, PWA), prioritized quick/medium/strategic tiers, and recommended implementation sequence.
- **docs/SCHEDULED_REPORTS.md** — scheduled executive reports: `scheduled_reports` table, Reports Hub schedule modal, cron runner (`scripts/run_scheduled_reports.php`), catalog slugs, saved-view `saved_view:{id}` slugs, PDF/XLSX email formats, verification commands.
- **docs/SAVED_REPORT_VIEWS.md** — custom report builder: `saved_report_views` table, save-view on tickets/equipment/expenses, Reports Hub **My reports**, JSON run API, export/share, owner email schedules, filter whitelist, verification commands.
- **docs/AUTOMATION_RULES.md** — workflow automation rules: `automation_rules` / `automation_rule_runs`, trigger dispatch (`includes/itm_automation_rules.php`), ticket hooks, cron runner, CRUD module, verification script.
- **handoff.md** — Project transition and transition/ownership handoff document for incoming developers/agencies, detailing technical layout, systems context, and vulnerability history.
- **list_soft-delete.txt** — scaffold CRUD module slugs in scope for soft-delete + audit-column UI (`$uiColumns` + `cr_manageable_columns`), plus status-driven modules `employees`, `equipment`, `patches_updates`, `tickets` (row `active` soft-delete mirror; business state on `*_statuses` FKs). Authoritative input for `scripts/apply_crud_audit_soft_delete.php` and related checks.
- **list_bespoke_UI.txt** — non-scaffold / bespoke module slugs deferred from that rollout.
- **scripts_errors.txt** — latest safe scripts matrix run report (tiers 1–3): counts, every result line, Passed (OK) list, Failures with root-cause classification. Regenerated by agents after a matrix run; not a product runtime file.

## 10. Common Pitfalls

- Register new or renamed canonical docs in this notes file and the `AGENTS.md` documentation table. [Cursor-Valid]
- Do not cite numbered pull requests or `/pull/<digits>` URLs in docs prose. [Cursor-Valid]
- Keep `list_soft-delete.txt` and `list_bespoke_UI.txt` in sync when adding modules or changing scaffold vs bespoke classification. [Cursor-Valid]
