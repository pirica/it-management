# AGENT_NOTES.md - contacts/api

## 1. Module Purpose
JSON/AJAX endpoints for the Contacts module. Handles inline field updates without full page reload.

## 2. Key Tables
- **contacts** (parent module table).

## 4. Business Rules (Critical for Agents)
- CSRF and company/user permission checks required on POST.
- `inline_edit.php` — async update handler.

## 7. File Structure
- `inline_edit.php` — inline edit API.
- `index.html` — directory listing guard.

## 8. Multi-Tenant Rules
- Scope all updates by `company_id` from session.

## 10. Common Pitfalls

- Non-admins may only inline-edit self when `type=emp`; department edits require admin. [Cursor-Valid]
- Allowlist fields only (`email`, `dect`, `phone`, …) — reject unknown POST keys. [Cursor-Valid]
- Always `itm_require_post_csrf()` and scope UPDATE by session `company_id`. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Parent module docs: `modules/contacts/AGENT_NOTES.md`.
