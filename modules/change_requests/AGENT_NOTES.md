# AGENT_NOTES.md - Change Requests (IT Change Management)

## 1. Module Purpose

IT change requests scoped per tenant with CMDB blast-radius CI selection, CAB approvals, optional ticket link, calendar feed, reminder emails, and automation/webhook events.

## 2. Key Tables

- **change_requests** — header (`change_type`, `risk_level`, `rollback_plan`, `ticket_id`, `status`, schedule, `reminder_sent_at`)
- **change_request_configuration_items** — affected CI links from impact picker
- **change_request_cab_members** — CAB roster per company (seed: tenant Admin role)
- **change_request_approvals** — per-change CAB decisions (`pending` / `approved` / `rejected`)
- **change_request_settings** — `reminder_days_before` (default 1)

## 3. Required Relationships

- **change_requests** → **configuration_items** (`source_configuration_item_id`, RESTRICT)
- **change_requests** → **tickets** (`ticket_id`, SET NULL)
- **change_request_configuration_items** → **change_requests**, **configuration_items** (CASCADE)
- **change_request_approvals** → **change_requests**, **employees** (approver)

## 4. Business Rules (Critical for Agents)

- Source CI is the change target; affected CIs from BFS impact graph checkboxes on create/edit.
- Status workflow: `draft` → `submitted` (CAB) → `approved` / `rejected` → `implemented` / `cancelled`.
- Non-admins cannot set `approved` / `rejected` directly; CAB quorum or Approval Inbox drives those transitions.
- Emergency changes: CAB quorum = 1 approval; standard/normal = all CAB members.
- Approval Inbox stages: `cab_{employee_id}`; adapter slug `change_requests`.
- Soft-delete only on `change_requests`; junction rows soft-deleted when replaced on save.

## 5. UI Behavior Requirements

- **create.php** / **edit.php** — change type, risk, rollback, ticket link, gated status list, CMDB impact graph
- **view.php** — CAB approval table, linked ticket, rollback plan, affected CI list + mini graph
- **index.php** — type/risk columns; search includes rollback text

## 6. API Actions (If Applicable)

- Impact data via [modules/configuration_items/api.php](http://localhost/it-management/modules/configuration_items/api.php?action=impact&id=1) (`action=impact`).
- Events: `change.submitted`, `change.approved`, `change.rejected`, `change.status_changed`, `change.implemented`.

## 7. File Structure

- **index.php** — list + search; empty-state **Add sample data**
- **create.php** — create/edit form (`edit.php` wrapper)
- **view.php** — detail + CAB table + mini graph
- **delete.php** — soft-delete handler

## 8. Multi-Tenant Rules

- All queries filter `company_id` from session.
- Linked `ticket_id` must belong to the same `company_id`.

## 9. Audit Logging Requirements

- `trg_change_requests_audit_*`, junction/CAB/settings/approval triggers in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Submitted seeds without approval rows: `itm_change_request_ensure_submitted_cab_state()` on view/sync.
- Do not bypass CAB by setting `approved` on create/edit unless `itm_is_admin()`.
- Calendar shows only `submitted`, `approved`, `implemented` with `scheduled_start` set.

## 11. Examples of Safe Code Patterns

```php
$result = itm_change_request_save($conn, $companyId, $employeeId, $id, $data, $ciIds);
itm_change_request_apply_cab_decision($conn, $companyId, $changeRequestId, $approverId, 'approve');
itm_change_request_process_reminders($conn, $companyId);
```

## 12. Module Owner Notes (Optional)

- Canonical doc: `docs/CHANGE_MANAGEMENT.md`
- Helpers: `includes/itm_change_requests.php`
- Migration: `db/migrations/change_requests_itsm.sql`
- Regression: `php scripts/verify_change_requests.php`; reminders: `php scripts/run_change_request_reminders.php`
