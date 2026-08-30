# Ticket Productivity Pack (phase 1)

Canned responses CRUD, comment picker (**Shift+F2**; F2 = @mention), ticket merge, public CSAT (`ticket-csat.php`). **Ticket surveys** — multi-question questionnaires, `ticket-survey.php`, dashboard, list filters (`docs/TICKET_SURVEYS.md`). Reports Hub shows a 12-month CSAT trend chart via `get_ticket_csat_trend()` on [modules/reports/index.php](http://localhost/it-management/modules/reports/index.php).

Regression: `php scripts/verify_ticket_productivity.php` · `php scripts/verify_ticket_surveys.php`

See [modules/ticket_canned_responses/index.php](http://localhost/it-management/modules/ticket_canned_responses/index.php) (Admin session).
