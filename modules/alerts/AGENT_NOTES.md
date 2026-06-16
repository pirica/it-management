# AGENT_NOTES.md - Alerts

## 1. Module Purpose
This module manages notifications and alerts within the system. It supports both "Global" alerts (visible to everyone in a company) and "Private" alerts (assigned to a specific user).

## 2. Key Tables
- **alerts** — main storage for alert messages, timing, and assignments.

## 3. Required Relationships
- **alerts** → depends on **companies** (via `company_id`).
- **alerts** → depends on **event_categories** (via `category_id`).
- **alerts** → depends on **users** (via `assigned_to_user_id` for private alerts and `created_by_user_id`).

## 4. Business Rules (Critical for Agents)
- **Visibility Logic**:
    - **Global Alerts**: Records where `assigned_to_user_id IS NULL` are visible to all users in the company.
    - **Private Alerts**: Records where `assigned_to_user_id = $user_id` are visible only to that user and the creator.
- **Visibility Helpers**: Always use `includes/alerts_visibility.php` to generate SQL conditions for visibility.
- **ICS Support**: Supports importing events from ICS files.

## 5. UI Behavior Requirements
- **Contextual Visibility**: The list view must filter alerts based on the logged-in user's identity and the global/private rules.
- **CSRF Protection**: All forms and actions must be protected by CSRF tokens.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import of alerts.

## 7. File Structure
- **index.php** — list view with visibility filtering and bulk actions.
- **create.php**, **edit.php**, **view.php** — standard CRUD wrappers.
- **delete.php** — handles deletion.

## 8. Multi-Tenant Rules
- All queries must be scoped by `company_id`.
- Use the helper functions in `includes/alerts_visibility.php` to ensure tenant and user-level isolation.

## 9. Audit Logging Requirements
- Mutations are logged via database triggers (`trg_alerts_audit_*`) into `audit_logs`.

## 10. Common Pitfalls
- **Leaking Private Alerts**: Failing to include the `assigned_to_user_id` check in custom queries can leak private notifications to other users.
- **Date/Time Formatting**: Ensure `start_datetime` and `end_datetime` are handled correctly for SQL and UI display.

## 11. Examples of Safe Code Patterns

### Safe SELECT with Visibility
```php
require_once '../../includes/alerts_visibility.php';
$visibilitySql = itm_alerts_visibility_sql('a');
$sql = "SELECT a.* FROM alerts a WHERE a.company_id = ? AND ($visibilitySql)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO alerts (company_id, title, description, assigned_to_user_id, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issii", $companyId, $title, $description, $assignedId, $creatorId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The alerts module is closely related to the Calendar/Events module. Ensure consistency in category usage.
