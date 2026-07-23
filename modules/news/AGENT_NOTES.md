# AGENT_NOTES.md - News

## 1. Module Purpose

Multi-source news and security feed reader for signed-in IT Management users. Shows cached items from NVD CVE advisories and external RSS/Atom feeds (Microsoft blogs, MSRC Security Update Guide, Windows 10/11 KB update feeds) inside a dual-pane layout with a source **Select** in the secondary sidebar (Todo-style). No cron — first visitor of the day triggers refresh; stale cache served while background worker updates.

## 2. Key Tables

None — external APIs/RSS + file cache only.

Registry row: **`modules_registry`** slug `news` (sidebar + company module access).

## 3. Required Relationships

- **`modules_registry`** (`module_slug = news`) — company gate via `company_module_access`
- No FK tables; feed content is global (not scoped by `company_id`)
- NVD CVE source complements tenant vulnerability rows in `modules/patches_updates/` (`cve` column) but does not duplicate them in MySQL

## 4. Business Rules (Critical for Agents)

- Feed sources defined in `news_feed_source_catalog()` (`includes/itm_news_feed.php`):
  - `nvd_cve` — NVD REST API 2.0 (shows CVSS severity/score columns); fetches CVEs **published in the last 120 days** via `pubStartDate` / `pubEndDate` (`news_build_nvd_api_query_params()`), newest first — never bare `startIndex=0` (returns 1990s records)
  - `ms_commandline` — `https://devblogs.microsoft.com/commandline/feed/`
  - `ms_windows_blog` — `https://blogs.windows.com/feed/`
  - `ms_powershell` — `https://devblogs.microsoft.com/powershell/feed/`
  - `ms_msrc_security` — `https://api.msrc.microsoft.com/update-guide/rss` (CVEs, advisories, Patch Tuesday)
  - `ms_win10_updates` — Microsoft Support Atom feed for Windows 10 KB/monthly updates
  - `ms_win11_updates` — Microsoft Support Atom feed for Windows 11 KB/monthly updates
- **NVD API key:** optional `NVD_API_KEY` or `ITM_NVD_API_KEY` in project root `.env` (see `.env.example`; loaded by `config/config.php`); sent as `apiKey` request header for higher NVD rate limits
- Feed items are sorted **newest first** by `published` (fallback `last_modified`) in `news_sort_items_newest_first()` before cache save and UI load
- Cache TTL: **24 hours** per source (`NEWS_CACHE_DURATION`)
- Per-source lock files: `modules/news/cache/{source_id}.lock` (5-minute stale timeout)
- **RBAC exempt:** slug `news` in `itm_crud_rbac_exempt_module_slugs()` — no `role_module_permissions` row required
- Do **not** call `itm_require_crud_role_module_permission()` in this module

## 5. UI Behavior Requirements

### Bespoke read-only dual-pane module

- **`index.php`** — Todo-style `.news-container` with `.news-sidebar` + `.news-content`
- Secondary sidebar includes **Source** `<select name="source">` (auto-submit on change) listing all catalog feeds; CVE (NVD) is one option
- Main table columns adapt: NVD shows Severity + Score; RSS sources show Title only
- Toolbar: RSS link (`feed.php?source=`), manual refresh POST with CSRF
- Actions column: `class="itm-actions-cell"` + `data-itm-actions-origin="1"`
- Dynamic browser title via `itm_resolve_module_sidebar_icon()`

### Sidebar (app)

- Planning section item id `news`, label `📰 News` (`includes/ui_config.php`)

## 6. API Actions (If Applicable)

- **`feed.php?source={id}`** — RSS/XML proxy cache endpoint; headers `X-Cache-Status`, `X-Feed-Source`, `X-Cache-Age`
- **`index.php` POST `refresh_cache`** — CSRF-protected manual refresh for selected `source`

## 7. File Structure

- **`index.php`** — authenticated HTML UI with source select sidebar
- **`feed.php`** — RSS endpoint per source (`news_handle_feed_request()`)
- **`background-update.php`** — CLI worker; argv[1] = source id
- **`news_feed_bootstrap.php`** — loads `config/config.php` + `includes/itm_news_feed.php`
- **`cache/`** — runtime per source: `{source_id}-feed.xml`, `{source_id}-feed.json`, `{source_id}.lock`
- **`includes/itm_news_feed.php`** — source catalog, fetch, cache, lock, background spawn

## 8. Multi-Tenant Rules

- Requires signed-in session and active `company_id`
- Feed data is not tenant-specific; same cache files for all companies
- Company module access must allow slug `news`

## 9. Audit Logging Requirements

No database writes — no audit triggers or `audit_logs` rows.

## 10. Common Pitfalls

- Add new feeds in **`news_feed_source_catalog()`** only — do not hardcode URLs in `index.php`
- `background-update.php` requires an existing lock (spawned from UI/feed paths)
- Windows Laragon: `news_resolve_php_binary()` falls back to full PHP 7.4.33 path when `PHP_BINARY` is empty
- Use `news_ensure_cache_dir()` for cache directory (not bare `mkdir()`)
- Module slug is **`news`** (not `cve`); old `modules/cve/` path is retired
- NVD fetches without `NVD_API_KEY` still work but hit stricter public rate limits

## 11. Examples of Safe Code Patterns

```php
$sourceId = trim((string)($_GET['source'] ?? 'nvd_cve'));
$cacheResult = news_ensure_cache_for_ui($sourceId, false);
$items = $cacheResult['items'];
```

## 12. Module Owner Notes (Optional)

- Related: `modules/patches_updates/` (tenant CVE tracking column)
- To add a feed: extend `news_feed_source_catalog()` with `type` = `rss` or `nvd`
