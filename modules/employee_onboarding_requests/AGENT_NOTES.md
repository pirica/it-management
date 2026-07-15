# AGENT_NOTES.md - Employee Onboarding Requests

## 1. Module Purpose
Manages requests for setting up new employees, including system access and equipment needs.

## 2. Key Tables
- **employee_onboarding_requests** — stores onboarding request details.

## 3. Required Relationships
- **employee_onboarding_requests** → depends on **companies**.
- **employee_onboarding_requests** → depends on **departments**.
- **employee_onboarding_requests** → depends on **employee_positions**.

## 4. Business Rules (Critical for Agents)
- **Workflow State**: Tracks status from "Pending" to "Completed".
- **Unique Request**: Typically one active request per new hire candidate.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Checklist**: Often used as a checklist for IT/HR tasks.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.
- **Approval email** — `cr_onboarding_send_approval_email_via_api()` sends HOD/HRD/ISM/GM/FIN approval requests via `itm_send_email()` and the tenant default SMTP profile in **Email Management** (`modules/emails/`). Optional seventh arg passes `email_template` options (primary **View request** CTA). Logs appear in **emails** send log.
- **Display helper** — `cr_onboarding_display_value($value, $isDateField, $fieldName)` requires `$fieldName` when calling `itm_format_cell_scalar_display()` (fixes undefined `$field` notice).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- Failing to update the status when all tasks are finished. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM employee_onboarding_requests WHERE company_id = ? AND status = 'Pending'");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO employee_onboarding_requests (company_id, candidate_name, department_id, position_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isii", $companyId, $name, $deptId, $posId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The bridge between HR and IT for new hires.
