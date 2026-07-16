# Request Password Module

## Purpose
Handles user requests for password changes/resets. Requires a multi-stage approval workflow involving HR and HOD before ISM can finalize the request.

## Tables
- `request_password`: Main table storing request details, status, and signature dates. Includes standard metadata columns (`active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`).
- `employees`: Linked for applicant and "requested by" details.
- `departments`: Linked for applicant's department name.
- `employee_system_access`: Used to populate the available applications for which a password can be requested.
- `approvers` / `approver_type`: Used to find HR and HOD emails for authorization links.

## Business Rules
- **Non-editable fields**: Name, Department, Username are pulled from the logged-in employee record.
- **Applications**: Only systems marked as '1' in `employee_system_access` for the employee are shown. Application discovery skips identity/audit/soft-delete meta columns (`id`, `company_id`, `employee_id`, `active`, `created_*`, `updated_*`, `deleted_*`, and legacy `changed_at` if present).
- **Create/Edit UI:** matches standard CRUD create layout — emoji-only `h1` (➕/✏️), policy banner (`.request-header`), then `form.form-grid` with stacked `.form-group` fields, reason radios via `.itm-checkbox-control.rp-reason-option`, and a leaf `.form-actions` Save/Back bar (no wrapping `.card` around fields+buttons). `js/ui-layout.js` `back_save_position` must only restyle that action bar.
- **Index list table:** `data-itm-db-import-endpoint="index.php"` on the list `<table>`; Actions header and body cells use `class="itm-actions-cell"` + `data-itm-actions-origin="1"` (body wraps controls in `.itm-actions-wrap`). JSON Import Excel is handled via `itm_handle_json_table_import($conn, 'request_password', …)` on POST. Do not drop these markers when editing list actions (e.g. adding delete) — `php scripts/check_index_table_compliance.php` fails if the header/import attributes regress.
- **Delete (creator only):** soft-delete allowed only when `created_by` matches the logged-in `employee_id` (legacy empty `created_by` falls back to applicant `employee_id`). List/view show 🗑️ only for the creator; delete POST re-checks before soft-delete.
- **Workflow**:
  1. Applicant submits request (Applicant Signature Date saved).
  2. Emails sent to HR/HOD via "Submit Email" buttons in View mode.
  3. HR and HOD authorize/decline via one-click links in email.
  4. ISM "Submit Email" button only enables after BOTH HR and HOD have 'Approved'.
  5. ISM submits email to applicant, saving ISM Signature Date.
- **Date Format**: DD/MM/YYYY in UI.

## Security
- Authorize/Decline links use HMAC-SHA256 signed tokens to prevent tampering.
- CSRF protection on all POST actions.
- Multi-tenancy strictly enforced via `company_id`.
- Soft delete pattern implemented: deleting a request password record updates `active = 0`, sets `deleted_by` and `deleted_at`, rather than hard-deleting the database row.
- Audit triggers are defined in `database.sql` for INSERT, UPDATE, and DELETE actions on `request_password`, which capture both old and new states in the `audit_logs` table.

## 10. Common Pitfalls

- Soft-delete only (`active = 0` with `deleted_by` / `deleted_at`) — do not hard-DELETE request rows. [Cursor-Valid]
- Only the creator may delete — enforce in UI and on the delete POST. [Cursor-Fixed]
- Do not regress list `data-itm-db-import-endpoint` or Actions header `data-itm-actions-origin="1"` when changing row actions. [Cursor-Fixed]
- ISM final notification must wait until both HR and HOD are Approved. [Cursor-Valid]
- Approval links use HMAC-SHA256 — verify with `hash_equals`; do not weaken token/secret handling. [Cursor-Valid]
- Application dropdown built from `employee_system_access` must skip audit/meta columns or non-system flags appear as apps. [Cursor-Fixed]
- Do not wrap create fields and Save/Back in one `.card` that `ui-layout.js` can mistake for the action bar — keep a dedicated `.form-actions` row. [Cursor-Fixed]
