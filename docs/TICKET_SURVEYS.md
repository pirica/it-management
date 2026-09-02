# Ticket Surveys (post-ticket CSAT)

Configurable multi-question satisfaction surveys issued after ticket resolution. Replaces the legacy single-score `ticket-csat.php` flow with questionnaire templates, public submit pages, admin review, KPI dashboard, list filters, automation, and webhooks.

## Overview

| Piece | Location |
|-------|----------|
| Questionnaire CRUD | [modules/ticket_questionnaires/](http://localhost/it-management/modules/ticket_questionnaires/index.php) |
| Issued surveys (read-only) | [modules/ticket_surveys/](http://localhost/it-management/modules/ticket_surveys/index.php) |
| KPI dashboard | [modules/ticket_survey_dashboard/](http://localhost/it-management/modules/ticket_survey_dashboard/index.php) |
| Core helpers | `includes/itm_ticket_survey.php` |
| Public submit (no login) | [ticket-survey.php](http://localhost/it-management/ticket-survey.php?token=…) |
| Legacy CSAT redirect | [ticket-csat.php](http://localhost/it-management/ticket-csat.php?token=…) → pending `ticket-survey.php` when a survey exists |
| Ticket issue UI | [modules/tickets/view.php](http://localhost/it-management/modules/tickets/view.php) — **Issue survey** + questionnaire picker |
| List filters / saved views | `includes/itm_tickets_list_query.php`, `includes/itm_saved_reports.php` (`survey_status`, `csat_min`, `survey_summary` column) |
| Regression | `php scripts/verify_ticket_surveys.php` |

Canonical companion: [docs/TICKET_PRODUCTIVITY.md](TICKET_PRODUCTIVITY.md) (canned responses, merge, legacy CSAT).

## Tables

| Table | Purpose |
|-------|---------|
| `ticket_questionnaires` | Tenant templates: name, optional `category_id`, `is_default`, soft-delete audit columns |
| `ticket_questionnaire_questions` | Child rows: `sort_order`, `question_text`, `question_type` (`rating_1_5` \| `text`), `is_required` |
| `ticket_surveys` | Issued invite per ticket: `token`, `respondent_email`, `reference`, `completed_at`, `average_score`, `accept_feedback`, `issued_by_employee_id` |
| `ticket_survey_answers` | Per-question snapshots: `answer_rating`, `answer_text` (no `company_id` — scoped via `survey_id`) |

DDL: `db/01_schema.sql`. Seeds: default + hardware templates on company 1; `@replicate_source_company_id` copies questionnaires/questions to companies 2–5; demo completed/pending surveys on `TCK-CSAT-001` tickets (`db/02_data.sql`).

Audit triggers: `trg_ticket_questionnaires_audit_*`, `trg_ticket_questionnaire_questions_audit_*` in `db/03_triggers.sql`. Survey answer rows are child snapshots — no separate audit triggers on `ticket_surveys` / `ticket_survey_answers`.

## Public URLs

| URL | Auth | Behaviour |
|-----|------|-----------|
| `ticket-survey.php?token={64-char}` | None | Load questionnaire; POST submits ratings/text (requires session `csrf_token` via `itm_validate_csrf_token()`); sets `completed_at`, syncs `tickets.csat_score` / `csat_comment` |
| `ticket-csat.php?token=…` | None | Legacy HMAC token from `includes/itm_ticket_csat.php`; redirects to latest **pending** survey URL when present |

Token builder: `itm_ticket_survey_build_public_url($token)` → `{BASE_URL}/ticket-survey.php?token=…`.

## Workflow

1. **Template** — Admin creates a questionnaire (default and/or category-scoped) with ordered questions.
2. **Issue** — Manual from ticket view, automation action `send_ticket_survey`, or auto on status → closed via `itm_ticket_survey_maybe_issue_on_close()` when **Configuration → Auto issue survey on close** is enabled on [modules/tickets/index.php?tab=configuration](http://localhost/it-management/modules/tickets/index.php?tab=configuration) (default **off** per company in `ticket_settings`).
3. **Respond** — Requester opens public URL; required ratings 1–5 and optional text; optional **accept feedback** checkbox.
4. **Sync** — `itm_ticket_survey_sync_csat_columns()` maps overall satisfaction + first text comment onto `tickets.csat_*` for Reports Hub CSAT trend.
5. **Review** — [modules/ticket_surveys/view.php](http://localhost/it-management/modules/ticket_surveys/view.php) shows answer snapshots; pending invites can be deleted from list.

**Merge:** `itm_ticket_merge_tickets()` calls `itm_ticket_survey_cancel_pending_for_ticket()` on the **source** ticket before consolidation. Canned responses may include `{{survey_url}}` — merged at render time via `itm_ticket_survey_merge_canned_body()` (pending survey only).

**Duplicate issue guard:** `itm_ticket_survey_issue()` returns existing row when a pending or completed survey already exists for the ticket (does not create a second invite after completion).

## Integration

### Automation (`includes/itm_automation_rules.php`)

| Trigger | When |
|---------|------|
| `ticket.survey_completed` | After successful `itm_ticket_survey_submit()` |

Context includes `ticket_id`, `survey_id`, `average_score`, plus standard ticket fields from `itm_automation_rules_build_ticket_context()`. Conditions support the usual `equals` / `contains` / `not_empty` ops on context fields (for example `average_score`).

| Action | Fields |
|--------|--------|
| `send_ticket_survey` | `ticket_id` (or context `ticket_id`), optional `questionnaire_id`, optional `send_email` (default 1) |

### Webhooks (`includes/itm_webhook_queue.php`)

Event `ticket.survey_completed` — enqueued on submit with `ticket_id`, `survey_id`, `average_score`, `ticket_external_code`, `title`. Subscribers configure the event in [modules/integration_webhooks/](http://localhost/it-management/modules/integration_webhooks/index.php).

### Saved report views

Tickets module whitelists `survey_status` (`pending` \| `completed` \| `none`) and `csat_min` (int). Column `survey_summary` available for export. See [docs/SAVED_REPORT_VIEWS.md](SAVED_REPORT_VIEWS.md).

### Notifications

Low average (≤ 2.0) notifies ticket assignee via `itm_notify_employee()` after submit.

## Stats dashboard

`itm_ticket_survey_stats_aggregate($conn, $companyId, $questionnaireId, $dateFrom, $dateTo)` returns issued/completed counts, response rate, 30-day average, NPS buckets from rating answers, and SLA-met vs breached score averages. UI: [modules/ticket_survey_dashboard/index.php](http://localhost/it-management/modules/ticket_survey_dashboard/index.php) with questionnaire and date filters.

## Commands

```bash
php scripts/verify_ticket_surveys.php
php scripts/verify_ticket_productivity.php   # canned responses, merge, legacy CSAT token
```

Browser: [verify_ticket_surveys.php?run=1](http://localhost/it-management/scripts/verify_ticket_surveys.php?run=1) (Admin session).

## Tenant configuration

Per-company toggles live in **`ticket_settings`** (one row per `company_id`). UI: [modules/tickets/index.php?tab=configuration](http://localhost/it-management/modules/tickets/index.php?tab=configuration) (Admin session with Tickets edit permission).

| Toggle | Default | Effect |
|--------|---------|--------|
| Auto issue survey on close | **Off** | When on, closed status transitions call `itm_ticket_survey_maybe_issue_on_close()` |
| Email survey link on issue | On | When on, `itm_ticket_survey_issue()` sends requester email (auto + manual issue) |
| SLA on new tickets | On | When on, `itm_ticket_sla_apply_on_create()` stamps SLA due dates |

Manual **Issue survey** on ticket view is unchanged. Automation `send_ticket_survey` is not gated by the auto-issue toggle. Migration: `db/migrations/ticket_settings.sql`.

## Sample data

- **Import seeds:** companies 1–5 questionnaires; company 1 completed hardware survey on ticket 6; companies 2–3 completed default surveys; companies 4–5 pending invites on `TCK-CSAT-001`.
- **Add sample data:** `db/02_data_sample.sql` templates for `ticket_questionnaires` + three default questions (company `1` marker; seeder stamps active tenant).
