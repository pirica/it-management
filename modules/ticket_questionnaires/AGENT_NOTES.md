# AGENT_NOTES — modules/ticket_questionnaires

Tenant-scoped post-ticket survey templates (`ticket_questionnaires`) with child rows in `ticket_questionnaire_questions` (sort order, rating/text type, required flag). Flattened CRUD with inline question editor on create/edit; view lists questions. Optional `category_id` → `ticket_categories`; `is_default` marks the company default template. Soft-delete on parent; questions are delete/reinsert on save.
