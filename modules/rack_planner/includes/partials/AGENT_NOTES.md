# AGENT_NOTES.md - rack_planner/includes/partials

## 1. Module Purpose
View partials for rack canvas rendering (`render.php`) and related markup.

## 5. UI Behavior Requirements
- Render "Status" badges on list rows based on `status_id` and corresponding human-readable labels from `rack_statuses`.
- Hide the internal `active` column from user forms and default list rows.
- Provide a `status_id` dropdown select box on the create/edit forms with support for the `__add_new__` dynamic whitelisted quick-add modal.
- Preserve drag/drop and autosave hooks wired from parent `index.php`.
- Disable default `table-tools.js` exports on custom layouts when redundant.

## 12. Module Owner Notes (Optional)
See `modules/rack_planner/includes/AGENT_NOTES.md` for price-sync rules.
