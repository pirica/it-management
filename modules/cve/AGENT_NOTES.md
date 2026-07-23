# AGENT_NOTES.md - CVE Feed

## 1. Module Purpose

Read-only NVD CVE advisory viewer for signed-in IT Management users. Fetches the latest CVEs from the [NVD REST API 2.0](https://services.nvd.nist.gov/rest/json/cves/2.0), caches RSS + JSON on disk (no cron), and renders an HTML list in the standard app shell. Complements tenant-scoped vulnerability rows in `modules/patches_updates/` but does not store CVEs in MySQL.

## 2. Key Tables

None — external API + file cache only.

Registry row: **`modules_registry`** slug `cve` (sidebar + company module access).

## 3. Required Relationships

- **`modules_registry`** (`module_slug = cve`) — company gate via `company_module_access`
- No FK tables; feed data is global (not scoped by `company_id`)

## 4. Business Rules (Critical for Agents)

- Cache TTL: **24 hours** (`CVE_CACHE_DURATION` in `includes/itm_cve_feed.php`)
- At most one refresh at a time via `modules/cve/cache/update.lock` (5-minute stale lock timeout)
- **First request of a new day:** synchronous NVD fetch (user may wait a few seconds)
- **Later expired requests:** serve stale cache + spawn `background-update.php`
- **No cache on first run:** synchronous initial fetch
- **RBAC exempt:** slug `cve` is in `itm_crud_rbac_exempt_module_slugs()` — any signed-in user with company module access may view; no `role_module_permissions` row required
- Do **not** call `itm_require_crud_role_module_permission()` in this module

## 5. UI Behavior Requirements

### Bespoke read-only module

- **`index.php`** — HTML table: CVE ID, severity badge (no emoji in badges), CVSS score, published date (`dd/mm/yyyy` via `itm_format_date_display`), description excerpt, NVD link (`🔎` with `title="View on NVD"`)
- Page heading: emoji-only `h1` with `title="CVE Feed"`
- Toolbar: RSS link (`feed.php`, `📡`), manual refresh POST (`refresh_cache` + CSRF, `🔄`)
- Actions column: `class="itm-actions-cell"` + `data-itm-actions-origin="1"`
- Table opts out of import/export: `data-itm-no-import-excel`, `data-itm-no-export-excel`, `data-itm-no-export-pdf`
- Dynamic browser title via `itm_resolve_module_sidebar_icon()`

### Sidebar

- Planning section item id `cve`, label `🛡️ CVE Feed` (`includes/ui_config.php`)

## 6. API Actions (If Applicable)

- **`feed.php`** — public RSS XML for subscribers; sets `X-Cache-Status` (`HIT`, `STALE`, `REFRESHED`, `INITIAL`) and `X-Cache-Age`
- **`index.php` POST `refresh_cache`** — CSRF-protected manual cache refresh (admin/user; no RBAC matrix row)

## 7. File Structure

- **`index.php`** — authenticated HTML list UI
- **`feed.php`** — RSS endpoint (`cve_handle_feed_request()`)
- **`background-update.php`** — CLI worker for non-blocking refresh (requires existing lock file)
- **`cve_feed_bootstrap.php`** — loads `config/config.php` + `includes/itm_cve_feed.php`
- **`cache/`** — runtime: `cve-feed.xml`, `cve-feed.json`, `update.lock` (created by helpers; not committed)
- **`includes/itm_cve_feed.php`** — shared cache, NVD fetch, RSS/JSON generation, lock + background spawn

## 8. Multi-Tenant Rules

- Requires signed-in session and active `company_id` (standard `config.php` enforcement)
- NVD feed content is **not** tenant-specific; same cache files for all companies
- Company module access must allow slug `cve` (seeded `enabled = 1` on fresh import)

## 9. Audit Logging Requirements

No database writes — no `audit_logs` rows and no audit triggers. NVD fetch/cache is outside MySQL.

## 10. Common Pitfalls

- Do not rename generic helpers without `cve_` prefix in `includes/itm_cve_feed.php` — avoids collisions with `logError`, `getCurrentUrl`, etc.
- `background-update.php` must only run when lock already exists (spawned from feed/UI paths)
- On Windows Laragon Apache, `cve_resolve_php_binary()` falls back to the full PHP 7.4.33 path when `PHP_BINARY` is empty
- Do not use bare `mkdir()` for `cache/` — use `cve_ensure_cache_dir()` → `itm_ensure_upload_directory()`
- Missing `triggerBackgroundUpdate` in the original drop-in broke stale-while-revalidate; logic lives in `cve_trigger_background_update()`

## 11. Examples of Safe Code Patterns

N/A for SQL — module does not query application tables for CVE data. Session gate is inherited from `config/config.php`.

Manual refresh pattern:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_cache'])) {
    itm_require_post_csrf();
    $cacheResult = cve_ensure_cache_for_ui(true);
}
```

## 12. Module Owner Notes (Optional)

- Related: `modules/patches_updates/` (tenant vulnerability tracking with `cve` column)
- Cache files under `modules/cve/cache/` are writable runtime artifacts; add `index.html` on new subfolders if created manually
