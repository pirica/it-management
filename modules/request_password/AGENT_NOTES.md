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
- **Applications**: Only systems marked as '1' (Active) in `employee_system_access` for the employee are shown.
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
- ISM final notification must wait until both HR and HOD are Approved. [Cursor-Valid]
- Approval links use HMAC-SHA256 — verify with `hash_equals`; do not weaken token/secret handling. [Cursor-Valid]
