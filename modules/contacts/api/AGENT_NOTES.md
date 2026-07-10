# AGENT_NOTES.md - contacts/api

## 1. Module Purpose
JSON/AJAX endpoints for the Contacts module (Protection Zone). Handles inline field updates without full page reload.

## 2. Key Tables
- **contacts** (parent module table).

## 4. Business Rules (Critical for Agents)
- **Protection Zone** — change only when explicitly requested.
- CSRF and company/user permission checks required on POST.
- `inline_edit.php` — async update handler.

## 7. File Structure
- `inline_edit.php` — inline edit API.
- `index.html` — directory listing guard.

## 8. Multi-Tenant Rules
- Scope all updates by `company_id` from session.

## 12. Module Owner Notes (Optional)
Parent module docs: `modules/contacts/AGENT_NOTES.md`.
