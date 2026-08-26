# AGENT_NOTES — modules/ticket_surveys

Read-only admin list of `ticket_surveys` rows (issued post-ticket CSAT links). JOINs `tickets` (reference / external code) and `ticket_questionnaires` (template name). `view.php` shows `ticket_survey_answers` snapshots. `delete.php` removes **pending** invites only (`completed_at IS NULL`); completed surveys are immutable. No create UI — surveys are issued from ticket workflows. RBAC via `itm_require_crud_role_module_permission` on slug `ticket_surveys`.
