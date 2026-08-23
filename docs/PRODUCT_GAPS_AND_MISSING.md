# Product gaps and missing work

Living inventory of **partial** and **not started** items from the Product & Architecture Feature Proposal (August 2026). Shipped features are listed only as context — see linked module docs and `verify_*` scripts for regression commands.

**Supersedes stale rows in** `docs/FEATURE_ROADMAP.md` (Phase 1 items marked proposed there are largely done).

---

## Executive summary

| Category | Done (representative) | Partial | Not started |
|----------|----------------------|---------|-------------|
| Core ITSM / ops | SLA dashboard, approvals inbox, automation, problem/KEDB, CMDB lite, ticket productivity | Change requests (CAB depth) | — |
| Integrations | Webhooks, SSO, Stripe, API v2 MVP | API v2 breadth | Network discovery |
| Hospitality | Promo code validation | — | — |
| Employee workflows | Appointment self-service core | Appointment UX polish, settings admin | Offboarding orchestration |
| Platform | CI quartet (smoke.yml) | PHP 8 migration, verify catalog vs CI | PWA, job queue, custom reports, vendor contracts |
| UX program | — | — | 58 bespoke modules (`docs/list_bespoke_UI.txt`) |

---

## Appointment pack (complete)

**Core proposal items — shipped**

| Item | Evidence |
|------|----------|
| Employee cancel / reschedule | `modules/appointments/api.php` — `cancel`, `reschedule_prepare`, `reschedule`; view toolbar 📅 / 🗑️ |
| My appointments filter | [list_all.php?filter=mine](http://localhost/it-management/modules/appointments/list_all.php?filter=mine) |
| Confirmation email + `.ics` | `itm_appointment_send_confirmation_email()` after schedule/reschedule |
| Past slots blocked | `itm_appointment_slot_is_past()` in grid + API |
| Booking disabled when settings off | `booking_enabled` / `active` gate + banner |
| List pagination + date range | `date_from`, `date_to`, search, sort, `records_per_page` |
| Staff status workflow | `scheduled`, `completed`, `no_show`, `cancelled` on list/view |

**Manual checks (open in a new browser tab):** [Booking](http://localhost/it-management/modules/appointments/index.php) · [List all](http://localhost/it-management/modules/appointments/list_all.php) · [My appointments](http://localhost/it-management/modules/appointments/list_all.php?filter=mine) · [Settings](http://localhost/it-management/modules/appointment_settings/index.php)

### Missing — booking UI (`modules/appointments/`)

| ID | Gap | Status |
|----|-----|--------|
| APPT-1 | Slot summary ISO dates | **Done** — `date_display` + `display_summary` in week_slots; JS `formatSlotDisplayValue()` |
| APPT-2 | Hardcoded `(BST)` | **Done** — sidebar uses `appointment_settings.timezone` |
| APPT-3 | ⚙️ Settings link RBAC | **Done** — `appt_user_can_access_settings()` |
| APPT-4 | MBQA bespoke smoke | **Done** — `appointments` in `mbqa_runner_bespoke_smoke_modules()` (Tier D list/search/sort) |

### Missing — admin settings (`modules/appointment_settings/`)

| ID | Gap | Suggested fix | Complexity |
|----|-----|---------------|------------|
| APSET-1 | No bulk “save all hours” grid | **Done** — weekly grid `#hours-grid` on hub; `itm_appointment_settings_save_business_hours_bulk()` |
| APSET-2 | Visit reasons: no drag sort | **Done** — drag reorder on [list_all.php](http://localhost/it-management/modules/appointment_settings/list_all.php); `js/appointment-settings.js` |
| APSET-3 | Duplicate visit reason names allowed | **Done** — `uq_appointment_visit_reasons_company_name` + `itm_appointment_settings_visit_reason_name_exists()` on create/edit |
| APSET-4 | Settings row delete vs re-ensure | **Done** — settings 🗑️ hidden; `delete.php` blocks with flash; ensure restores defaults |
| APSET-5 | Flash UX (`?msg=` only) | **Done** — `aps_flash_set()` / `aps_flash_render()` session banners |
| APSET-6 | No `verify_appointment_settings.php` | **Done** — [verify_appointment_settings.php?run=1](http://localhost/it-management/scripts/verify_appointment_settings.php?run=1) |
| APSET-7 | RBAC for ⚙️ on booking page | **Done** — `appt_user_can_access_settings()` |

### By design (not counted in the 90% pack)

- Booking `index.php` stays bespoke (slot modal) — no flattened scaffold bulk delete / Excel import on booking screen.
- Module remains on `docs/list_bespoke_UI.txt` (deferred soft-delete scaffold parity).

**Regression:** `php scripts/verify_appointment.php` · `php scripts/verify_appointment_settings.php` · Browser: [verify_appointment.php?run=1](http://localhost/it-management/scripts/verify_appointment.php?run=1) · [verify_appointment_settings.php?run=1](http://localhost/it-management/scripts/verify_appointment_settings.php?run=1) (Admin session)

---

## Twelve proposed features — status

| # | Feature | Status | What is still missing |
|---|---------|--------|------------------------|
| 1 | IT Change Management | **Partial** | `change_type`, risk, rollback, ticket link, Approval Inbox adapter, calendar feed, reminder cron, automation/webhook events |
| 2 | Problem / Known Error DB | **Done** | — (includes master tickets) |
| 3 | CMDB Lite | **Done** | Automated discovery → CI (see Feature 7) |
| 4 | Appointment pack | **Done** (core) | Optional: deeper MBQA booking steps |
| 5 | PWA field shell | **Missing** | manifest, service worker, `enable_pwa`, field layout |
| 6 | OpenAPI v2 gateway | **Partial (MVP)** | Resources beyond tickets + equipment; broader `api-examples/` |
| 7 | Network discovery | **Missing** | Profiles, SNMP staging queue, review UI, cron |
| 8 | Vendor contracts | **Missing** | Module, renewal alerts, contract links |
| 9 | Offboarding orchestration | **Missing** | Checklist tables, module, termination hooks |
| 10 | Bespoke UX parity | **Missing** | 58 modules in `docs/list_bespoke_UI.txt` |
| 11 | Custom report builder | **Missing** | `saved_report_views`, save-view on lists |
| 12 | Hotel promo validation | **Done** | — |

---

## Phase 1 roadmap items (FEATURE_ROADMAP.md) — reconciliation

| Roadmap item | Repo status |
|--------------|-------------|
| SLA Command Center | **Done** — `modules/ticket_sla_dashboard/`, `docs/TICKET_SLA_DASHBOARD.md` |
| Unified Approval Inbox | **Done** — `modules/approval_inbox/`, `docs/APPROVAL_INBOX.md` |
| Workflow automation rules | **Done** — `docs/AUTOMATION_RULES.md` |
| Scheduled executive reports | **Done** — `docs/SCHEDULED_REPORTS.md` |
| Hotel online payments (Stripe) | **Done** — `docs/STRIPE_CHECKOUT.md` |
| Ticket productivity pack | **Done** — `docs/TICKET_PRODUCTIVITY.md` |
| Asset lifecycle & depreciation | **Done** — `docs/ASSET_LIFECYCLE.md` |
| SSO / LDAP | **Done** — `docs/SSO_LDAP.md`, `docs/SSO_SAML.md` |
| Outbound webhooks | **Done** — `docs/INTEGRATION_WEBHOOKS.md` |
| CI Tier 2 + PHPUnit in GHA | **Done** — `.github/workflows/smoke.yml` (four jobs) |
| Appointment status workflow | **Done** — staff status badges + self-service cancel/reschedule |
| Vault org recovery | **Done** — `docs/VAULT.md` §2.E |
| PHP 8.2 certification | **In progress** — `handoff.md` §2.2 |

---

## Cross-cutting platform gaps

| ID | Gap | Notes |
|----|-----|-------|
| PLAT-1 | Customer / requester self-service portal | No magic-link ticket status; inbound email → tickets only |
| PLAT-2 | SAM / license compliance metering | License CRUD + expiry alerts; no seat reconciliation |
| PLAT-3 | Generic background job queue | Webhook delivery queue exists; no shared `job_queue` |
| PLAT-4 | ~119 `verify_*` scripts vs CI | Quartet in GHA; most verify scripts local-only |
| PLAT-5 | Private-module command palette | Vault modules intentionally excluded from `search_index` |
| PLAT-6 | `docs/FEATURE_ROADMAP.md` stale | Use this file for Phase 2 backlog |

---

## Suggested implementation order (appointment gaps only)

All APSET-1–7 items are **done**. Optional follow-ups: deeper MBQA booking steps on `modules/appointments/index.php`.

---

## Related docs

- `docs/APPOINTMENT.md` — booking API and slot contract
- `modules/appointments/AGENT_NOTES.md` — module rules and remaining backlog
- `modules/appointment_settings/AGENT_NOTES.md` — settings admin backlog
- `docs/FEATURE_ROADMAP.md` — historical Phase 1 proposal (partially stale)
