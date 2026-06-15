# AGENT_NOTES.md - Config

## 1. Module Purpose
Maintains system-wide configuration, database credentials, path constants, and core security functions.

## 2. Key Tables
- Interacts with **companies** for initial tenant resolution.

## 4. Business Rules (Critical for Agents)
- **Environment Variables**: Prefer loading secrets from environment variables (e.g., `ITM_DB_HOST`).
- **No PDO**: The system strictly uses `mysqli`.
- **Zero Dependencies**: Do not introduce external packages (Composer/NPM).

## 7. File Structure
- **config.php** — the core configuration file required by every entry point.

## 10. Common Pitfalls
- Committing secrets to version control.
- Modifying constants without checking their global impact.

## 11. Examples of Safe Code Patterns

### Safe Database Connection (via config.php)
```php
require_once 'config.php';
// $conn is now available
```

## 12. Module Owner Notes (Optional)
The single source of truth for system environment and security foundations.
