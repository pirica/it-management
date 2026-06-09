# AGENT_NOTES.md - Includes

## 1. Module Purpose
Contains shared PHP logic, helper functions, and visibility filters used across multiple modules.

## 2. Key Tables
- Primarily provides helper logic for **alerts**, **equipment**, and **audit_logs**.

## 3. Required Relationships
- Functions here often depend on `config/config.php`.

## 4. Business Rules (Critical for Agents)
- **Visibility Helpers**: `alerts_visibility.php` is mandatory for all alert-related queries.
- **Security Functions**: Use `itm_is_safe_identifier` for dynamic SQL identifiers.

## 7. File Structure
- **alerts_visibility.php** — centralized visibility logic for global/private alerts.
- **delete_functions.php** — shared logic for complex deletions (e.g., equipment).

## 11. Examples of Safe Code Patterns

### Using Alerts Visibility SQL
```php
require_once ROOT_PATH . 'includes/alerts_visibility.php';
$visibility = itm_alerts_visibility_sql('alias');
```

## 12. Module Owner Notes (Optional)
Centralized logic here prevents code duplication and ensures consistent security/visibility enforcement.
