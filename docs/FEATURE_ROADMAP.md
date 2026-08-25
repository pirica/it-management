# IT Management System — Product Feature Roadmap

> **Status:** Phase 1 items below are largely **shipped**. For current partial/missing work and appointment pack gaps, see **`docs/PRODUCT_GAPS_AND_MISSING.md`**. **Custom report builder (Feature 11)** is **done** — see **`docs/SAVED_REPORT_VIEWS.md`**.

## Executive summary

ITM is already a **broad multi-tenant operations platform** (ITAM, IPAM/DCIM, HR, helpdesk, finance, hospitality, vault productivity) with **222 module folders**, **224 tables**, mature RBAC, audit triggers, and an extensive `scripts/` verification catalog. Strengths: deep vertical coverage, tenant isolation, zero external dependencies, and strong security hardening.

The biggest **product gaps** are not missing CRUD tables but **incomplete workflows**, **limited automation**, **weak operational visibility**, and **enterprise integration/auth** layers on top of an otherwise feature-rich core. The highest ROI additions:

1. **SLA Command Center** — policies exist (`ticket_sla_policies`, `includes/itm_ticket_sla.php`) but no proactive breach management UI or scheduled enforcement.
2. **Unified Approval Inbox** — approvals scattered across `request_password`, `approvals`, `employee_onboarding_requests`, `forecast_revisions`.
3. **Workflow Automation Rules** — inbound email routing exists; no general if/then engine for tickets, alerts, equipment expiry.
4. **Scheduled Executive Reports** — **Shipped** — `scheduled_reports`, Hub modal, cron runner; saved views use `saved_view:{id}` (`docs/SCHEDULED_REPORTS.md`, `docs/SAVED_REPORT_VIEWS.md`).
5. **Hotel Online Payments** — explicitly deferred in `docs/BOOKING.md` and `handoff.md`.
6. **Ticket Productivity Pack** — canned responses, merge/split, satisfaction survey (SLA timestamps exist; UX incomplete).
7. **Asset Lifecycle & Depreciation** — equipment has warranty/certificate dates; no formal lifecycle states or financial depreciation.
8. **SSO / LDAP Authentication** — username/password only; blocks enterprise adoption.
9. **Outbound Webhooks & Integration Hub** — `integration_accounts` is a finance ledger table, not a connector framework; hotel distribution has webhooks but helpdesk/finance do not.
10. **CI Quality Gate Expansion** — product maturity gap: Tier 2 checks and PHPUnit not in `.github/workflows/smoke.yml`.

All proposals below follow existing conventions: procedural PHP, MySQLi prepared statements, `company_id` scoping, standard audit columns, `modules_registry` registration, and regression scripts per `scripts/SCRIPTS.md`.

---

## 1. Product gaps and opportunities

### Missing features (vs. market expectations)

| Gap | Current state | Opportunity |
|-----|---------------|-------------|
| **Proactive SLA management** | Policies + due timestamps on create; breach logged to `ticket_activity` only on manual check | IT command center with breach queue, escalations, email/in-app alerts |
| **Unified approvals** | 4+ bespoke approval flows | Single inbox with filters, delegation, SLA |
| **Automation beyond email** | Inbound email → tickets; email alert rules for expiry | Configurable tenant rules (priority bump, assign, notify, create ticket) |
| **Scheduled reporting** | **Shipped** — executive catalog + owner saved-view schedules (`docs/SCHEDULED_REPORTS.md`) | Additional report types / cross-module builder |
| **Online payments** | Payment-at-hotel only | Stripe checkout on guest portal; ledger sync |
| **Enterprise SSO** | Local auth + TOTP on vault | LDAP/SAML for login; keep vault separate |
| **Asset financial lifecycle** | `purchase_cost`, warranty fields | Depreciation schedule, disposal workflow, TCO |
| **Outbound integrations** | Hotel ARI webhooks only | Generic webhook dispatcher for tickets, bookings, expenses |
| **Mobile field experience** | Responsive CSS only | PWA shell for tickets, explorer, appointments |
| **Vault org recovery** | Zero-knowledge lockout by design (default) | Optional tenant policy: consent + escrow + audited admin recovery (`modules/vault_org_recovery/`) |

### Pain points (from docs and module notes)

- **Vault lockout** — no recovery path (`docs/VAULT.md`); HR offboarding loses encrypted data.
- **Appointment status** — `status = scheduled` only; no completed/no-show UI (`modules/appointments/AGENT_NOTES.md`).
- **Bespoke module inconsistency** — 58 modules defer soft-delete scaffold (`docs/list_bespoke_UI.txt`); uneven UX.
- **CI vs. catalog mismatch** — 109 `verify_*` scripts, 2 CI jobs; regressions slip through.
- **PHP 8 blocker** — modernization in progress (`handoff.md`).
- **API discoverability** — session/AJAX endpoints remain in `scripts/api.php`; **API v2** partner REST + OpenAPI catalog shipped (`docs/API_V2.md`, `scripts/openapi.php`).

### High-value improvements (leverage existing code)

- **CMDB Lite (delivered):** `configuration_items` / `configuration_item_types` / `configuration_item_relationships`, impact SVG graph (`js/itm-cmdb-impact-graph.js`), change-request blast-radius picker, auto-sync from equipment, IDF, racks, IP subnets, and system_access (`includes/itm_cmdb.php`). Regression: `php scripts/verify_cmdb.php`. Not D3 — vanilla SVG layout only.

- Extend `employee_notifications` + `email_alert_rules` patterns for SLA/automation.
- Reuse `reports/api/helpers.php` for scheduled report payloads.
- Reuse `itm_hotel_booking_distribution_webhooks.php` retry queue pattern for generic webhooks.
- Reuse `role_module_permissions` for new modules without new auth model.
- Reuse `share_sessions` / QR patterns for guest payment receipt links.

---

## 2. Proposed features (detailed specs)

### Feature A: SLA Command Center

**User problem:** IT managers cannot see which tickets are about to breach or have breached SLA; agents discover too late.

**Expected workflow:**

1. Admin configures policies in existing Ticket SLA Policies (`modules/ticket_sla_policies/`).
2. Agent opens **SLA Command Center** — tabs: At Risk (< 2h), Breached, Met.
3. Cron `php scripts/run_ticket_sla_monitor.php` runs every 15 min: stamps breaches, sends notifications, optional auto-escalate priority.
4. Ticket list shows SLA badges (green/amber/red) and countdown.

**Technical requirements:**

- New module `modules/ticket_sla_dashboard/` (bespoke UI, not flattened scaffold).
- Extend `includes/itm_ticket_sla.php`: `itm_ticket_sla_list_at_risk()`, `itm_ticket_sla_process_scheduled_breaches()`.
- Hook `itm_ticket_sla_stamp_first_response()` on comment create (`modules/ticket_comments/`).
- Emit `itm_notify_employee()` on breach.

**Database changes:**

- `tickets`: add `sla_response_breached_at`, `sla_resolve_breached_at` (nullable timestamps) — migration `db/migrations/ticket_sla_breach.sql` + mirror in `01_schema.sql`.
- Optional: `ticket_sla_escalation_rules` (`company_id`, `priority_id`, `escalate_to_employee_id`, `breach_type`).

**UI changes:**

- New sidebar entry under Management → **SLA Command Center**.
- Ticket `index.php` / `view.php`: SLA badge column and breach history from `ticket_activity`.

**API / backend:**

- `modules/ticket_sla_dashboard/api.php`: `GET ?action=summary`, `GET ?action=list&filter=at_risk|breached`.
- Cron script cataloged in `scripts/scripts.php`.

**Risks / dependencies:**

- Business hours vs. calendar hours for SLA (start with 24/7; phase 2 business hours).
- Timezone: use company setting or `appointment_settings.timezone` pattern.

**Complexity:** Medium

---

### Feature B: Unified Approval Inbox

**User problem:** Approvers must visit multiple modules (password reset, onboarding, budget forecasts) with no single queue.

**Expected workflow:**

1. Approver opens **Approval Inbox** — one list: pending items with type badge, requester, age, deep link.
2. Inline **Approve / Reject** with comment (where module supports it).
3. Email + bell notification on new items (extend existing emitters).

**Technical requirements:**

- Adapter registry in `includes/itm_approval_inbox.php` with per-source fetchers:
  - `request_password` (HR/HOD/ISM stages)
  - `employee_onboarding_requests`
  - `approvals` / `forecast_revisions`
- Each source module calls `itm_approval_inbox_upsert()` on create/status change.

**Database changes:**

```sql
approval_inbox_items (
  company_id, module_slug, record_id, approval_stage,
  title, requester_employee_id, assignee_employee_id,
  status ENUM('pending','approved','rejected','cancelled'),
  due_at, action_url, payload_json, standard audit columns
)
```

**UI changes:**

- `modules/approval_inbox/index.php` — filtered list, search, pagination (scaffold pattern).
- Badge count on sidebar + header bell category.

**API / backend:**

- `api.php`: `POST action=decide` proxies to source module handlers (CSRF + RBAC).

**Risks / dependencies:**

- Must not duplicate approval logic — inbox is read/decide router only.
- Source modules keep authoritative state.

**Complexity:** Medium–High

---

### Feature C: Workflow Automation Rules

**User problem:** Repetitive IT ops (warranty expiry → ticket, VIP assignee routing, after-hours alerts) require manual work.

**Expected workflow:**

1. Admin → **Automation Rules** → Create rule: Trigger + Conditions + Actions (notify, assign, change priority, create ticket, webhook).
2. Rules evaluated on event hooks and nightly cron for date-based triggers.
3. Execution log visible per rule (last 50 runs).

**Technical requirements:**

- New `includes/itm_automation_rules.php` — event dispatcher called from ticket/equipment/alerts save paths.
- Cron: `php scripts/run_automation_rules.php` for scheduled/date triggers.
- Reuse `email_alert_rules` UI patterns.

**Database changes:**

```sql
automation_rules (
  company_id, name, trigger_slug, conditions_json, actions_json,
  enabled, last_run_at, standard audit columns
)
automation_rule_runs (
  company_id, rule_id, status, message, context_json, ran_at
)
```

**Complexity:** High

---

### Feature D: Scheduled Executive Reports

**User problem:** Leadership wants weekly PDF/XLSX in inbox without logging in.

**Expected workflow:**

1. Admin configures schedule in Reports Hub: report type, frequency, recipients, format.
2. Cron generates file via existing helpers, emails via `itm_send_email()` with attachment.
3. Audit row per send in `emails` table.

**Database changes:**

```sql
scheduled_reports (
  company_id, report_slug, schedule_cron, recipients_json,
  format ENUM('pdf','xlsx'), last_sent_at, enabled, audit columns
)
```

**Complexity:** Medium

---

### Feature E: Hotel Online Payments (Stripe)

**User problem:** Guests expect pay-now; properties lose conversions with payment-at-hotel only.

**Technical requirements:**

- curl REST to `api.stripe.com` + webhook HMAC (no Composer).
- `includes/itm_stripe_checkout.php`, `booking/payment-stripe.php`, `booking/stripe-webhook.php`.
- PCI scope minimized via Stripe Checkout (no card data on ITM servers).

**Complexity:** High

---

### Feature F: Ticket Productivity Pack

**User problem:** Agents retype answers; no CSAT; hard to merge duplicate tickets.

**Components:** canned responses, ticket merge, CSAT survey on resolve.

**Complexity:** Medium

---

### Feature G: Asset Lifecycle and Depreciation

**User problem:** Finance needs asset book value and disposal audit; IT needs lifecycle states beyond equipment status.

**Complexity:** Medium

---

### Feature H: SSO / LDAP Login

**User problem:** Enterprises require Active Directory / Azure AD login.

**Complexity:** High

---

### Feature I: Outbound Webhook Integration Hub

**User problem:** External systems (Slack, Teams, ERP, Zapier) need real-time events from ITM.

**Complexity:** Medium

---

### Feature J: Progressive Web App (Field Technician Shell)

**User problem:** Technicians need quick mobile access to tickets, appointments, explorer Common folder on site.

**Complexity:** Medium

---

## 3. Implementation plan (cross-cutting)

### Step-by-step development approach (per feature)

1. **Discovery** — Read module `AGENT_NOTES.md`, `db/01_schema.sql`, existing helpers.
2. **Schema** — `db/migrations/{module}_{subject}.sql` + mirror `01_schema.sql`.
3. **Helpers** — `includes/itm_{feature}.php` (business logic, tenant-scoped).
4. **Module** — Flat CRUD or bespoke UI per `AGENTS.md`; register in `modules_registry`.
5. **Hooks** — Wire emitters into existing save paths.
6. **Scripts** — `verify_{feature}.php` + catalog entry in `scripts/scripts.php`.
7. **Docs** — `docs/{FEATURE}.md`, module `AGENT_NOTES.md`.
8. **QA** — `php -l`, `check_sql_injection_coverage.php`, feature verify script.

### Testing strategy

| Layer | Tool |
|-------|------|
| Static | `check_sql_injection_coverage.php`, `check_csrf_coverage.php`, `check_audit_logs_coverage.php` |
| Unit | PHPUnit in `phpunit/tests/Unit/Includes/` |
| Integration | `verify_{feature}.php` with disposable test employee |
| HTTP | MBQA for scaffold modules; bespoke scripts for workflows |
| CI uplift | Add `run_tier2_checks.php` + PHPUnit (DB-skipped) to smoke.yml |

---

## 4. Prioritized roadmap

### Quick wins (1–2 weeks each)

| Priority | Feature | Rationale |
|----------|---------|-----------|
| 1 | **F: Ticket Productivity Pack** (canned responses only) | Small schema; immediate agent value |
| 2 | **A: SLA Command Center** (dashboard + list badges) | Builds on existing `itm_ticket_sla.php` |
| 3 | **CI Tier 2 + PHPUnit in GitHub Actions** | Product quality; no user-facing risk |
| 4 | **Appointment status workflow** | Documented gap; small change |

### Medium features (1–2 months)

| Priority | Feature | Rationale |
|----------|---------|-----------|
| 5 | **B: Unified Approval Inbox** | Cross-module UX win |
| 6 | **D: Scheduled Executive Reports** | Reuses Reports Hub |
| 7 | **G: Asset Lifecycle & Depreciation** | Bridges IT + finance |
| 8 | **I: Outbound Webhook Hub** | Enables ecosystem |
| 9 | **J: PWA Field Shell** | Mobile without native apps |

### Strategic features (long-term, 3–6+ months)

| Priority | Feature | Rationale |
|----------|---------|-----------|
| 10 | **E: Hotel Online Payments (Stripe)** | Revenue impact; PCI effort |
| 11 | **C: Workflow Automation Rules** | Platform play |
| 12 | **H: SSO / LDAP** | Enterprise deals |
| 13 | **PHP 8.2 certification** | Modern hosting prerequisite |
| 14 | **Vault org recovery (optional policy)** | **Done** — company policy, employee consent, escrow, admin inbox + audit (`docs/VAULT.md` §2.E) |

---

## 5. Full feature list (summary table)

| # | Feature | Complexity | DB | UI | API/Cron |
|---|---------|------------|----|----|----------|
| A | SLA Command Center | Medium | Extend `tickets` | Dashboard + badges | `run_ticket_sla_monitor.php` |
| B | Unified Approval Inbox | Med–High | `approval_inbox_items` | Inbox module | Decide proxy API |
| C | Workflow Automation | High | `automation_rules` | Rules CRUD + log | Event hooks + cron |
| D | Scheduled Reports | Medium | `scheduled_reports` | Reports Hub modal | `run_scheduled_reports.php` |
| E | Stripe Payments | High | Payment columns | Guest checkout | Webhook endpoint |
| F | Ticket Productivity | Medium | Canned responses, CSAT | Comment picker | CSAT public token |
| G | Asset Lifecycle | Medium | Equipment columns | Timeline | Depreciation cron |
| H | SSO / LDAP | High | Company SSO fields | Login + settings | SAML/LDAP callbacks |
| I | Webhook Hub | Medium | Webhooks + deliveries | CRUD + test | Queue cron |
| J | PWA Field Shell | Medium | None v1 | manifest + SW | Cache policy |

---

## 6. Recommended first implementation sequence

1. **Week 1–2:** SLA dashboard (read-only) + ticket SLA badges + `run_ticket_sla_monitor.php`.
2. **Week 2–3:** Ticket canned responses + CI Tier 2 job.
3. **Month 2:** Approval inbox MVP (2 sources: `request_password`, `onboarding`).
4. **Month 2–3:** Scheduled reports (one report type: ticket volume).
5. **Month 3+:** Webhook hub OR asset lifecycle (based on customer vertical).

Each deliverable ships as a **fresh branch + PR** per `AGENTS.md`, with `verify_*` script, module `AGENT_NOTES.md`, and localhost verification links.
