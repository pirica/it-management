# AGENT_NOTES — modules/ticket_survey_dashboard

Read-only KPI dashboard for `ticket_surveys` / `ticket_survey_answers`. Calls `itm_ticket_survey_stats_aggregate()` in `includes/itm_ticket_survey.php` for response rate, 30-day average score, and NPS buckets (promoters/passives/detractors from rating answers). Filters: `questionnaire_id`, `date_from`, `date_to` (issued `created_at`). RBAC view on slug `ticket_survey_dashboard`. Pattern mirrors `modules/ticket_sla_dashboard/`.
