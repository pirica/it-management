# AGENT_NOTES.md - Config

## 1. Module Purpose
Maintains system-wide configuration, database credentials, path constants, and core security functions.

## 2. Key Tables
- Interacts with **companies** for initial tenant resolution.

## 4. Business Rules (Critical for Agents)
- **Environment Variables**: Prefer loading secrets from environment variables (e.g., `ITM_DB_HOST`).
- **No PDO**: The system strictly uses `mysqli`.
- **Zero Dependencies**: Do not introduce external packages (Composer/NPM).
- **Administrator helpers**: `itm_is_admin()` checks role/username; `itm_require_admin()` enforces admin access (HTTP 403 on POST, redirect on GET).
- **API rate-limit probe auth bypass**: `scripts/api.php?rate_limit=1` defines `ITM_API_RATE_LIMIT_PROBE` before loading `config.php`, which sets `$itmSkipWebAuth` so programmatic clients receive JSON instead of a login redirect. **Free** tier may omit `api_key` when the request carries an authenticated session; paid tiers still require `X-API-Key` / `api_key` via `itm_api_resolve_rate_limit_row()`.

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
