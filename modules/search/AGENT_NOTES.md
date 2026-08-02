# AGENT_NOTES.md - Search (command palette)

## 1. Module Purpose
Global **command palette** search across enabled tenant modules so users can find people, assets, tickets, IP addresses, and catalog rows without opening each module first.

- **Phase 1 (live):** `includes/itm_command_palette_search.php` runs scoped SQL `LIKE` queries per module (no vault/private modules).
- **Phase 2 (live):** `includes/itm_search_index.php` maintains denormalized `search_index` rows (FULLTEXT) on CRUD for employees, equipment, tickets, ip_addresses, catalogs. Palette prefers index when populated, then falls back to phase 1 SQL `LIKE`.

## 2. Key Tables
- **search_index** — phase 2 denormalized palette index (`company_id`, `module_slug`, `record_id`, `title`, `subtitle`, `keywords`, FULLTEXT on text columns). Synced on CRUD via `itm_search_index_after_module_*()`; backfill: `php scripts/apply_search_index_backfill.php --apply`.

## 3. Required Relationships
- **search_index** → **companies** (`company_id`, `ON DELETE CASCADE`).
- Palette queries read live rows from **employees**, **equipment**, **tickets**, **ip_addresses**, **catalogs** (tenant-scoped).

## 4. Business Rules (Critical for Agents)
- **Enabled modules only:** `has_module_access()` company gate + `itm_sidebar_item_passes_role_view()` RBAC `can_view` per slug.
- **Employees:** admin-only (`itm_is_admin()`) — mirrors `modules/employees/` entry guards even though employees is RBAC-exempt for CRUD helpers.
- **No vault modules:** passwords, notes, todo, private_contacts, bookmarks, explorer files, etc. are intentionally excluded.
- **Minimum query length:** 2 characters (`itm_command_palette_search()` and UI debounce).
- **Per-module cap:** default 5 results per group (`limit` query param / JSON field, max 10).
- **Soft-delete:** respect `deleted_at IS NULL` on scaffold tables; employees also hide `is_hidden = 1`.
- **Search helpers:** employees → `itm_employees_build_search_conditions()`; equipment → `itm_equipment_build_search_where_sql()` + joins; IP → `itm_ipam_fetch_address_list()`; tickets/catalogs → prepared `LIKE` on scalar + label columns.

## 5. UI Behavior Requirements
- Header **🔍** button (`data-itm-command-palette-open="1"`, `title="Search (Ctrl+K)"`) in `includes/header.php`.
- **Ctrl+K** / **Cmd+K** opens modal (`js/command-palette.js`); **Esc** closes; **↑/↓** + **Enter** navigate results.
- Results grouped by module type with sidebar icon + label; click/Enter opens `modules/{slug}/view.php?id=`.
- Styles: `css/styles.css` → `.itm-command-palette-*`.

## 6. API Actions
- **`modules/search/api.php`**
  - **GET** `?q=` + optional `limit` (session auth, rate limit via `itm_api_enforce_rate_limit_or_exit`).
  - **POST** JSON `{ "query": "...", "csrf_token": "..." }` (CSRF required).
  - Response: `{ "query": "...", "groups": [ { "module_slug", "label", "icon", "results": [ { "id", "title", "subtitle", "url" } ] } ] }`.

## 7. File Structure
- `api.php` — JSON endpoint.
- `index.php` — redirects to dashboard (palette is global, not a sidebar CRUD screen).
- `index.html` — directory listing guard.

## 8. Multi-Tenant Rules
- All queries scoped by session `company_id`.

## 9. Audit Logging Requirements
- Read-only API; no `audit_logs` rows. Phase 2 index updates on CRUD will be non-audited cache rows.

## 10. Common Pitfalls
- Returning employees for non-admin users (must call `itm_command_palette_user_can_search_module()` before each entity search).
- Searching vault ciphertext columns — stay on phase 1 scalar/LABEL paths only.
- Forgetting IPAM equipment join backfill — `itm_ipam_fetch_address_list()` handles backfill internally.

## 11. Examples of Safe Code Patterns

### RBAC-gated unified search
```php
$payload = itm_command_palette_search($conn, $companyId, $employeeId, $query, 5);
```

### Regression
```bash
php scripts/verify_command_palette_search.php
php scripts/apply_search_index_backfill.php --apply
```

## 12. Change Log (optional)
- Phase 1 command palette: SQL LIKE API + header modal UI.
- Phase 2: `search_index` FULLTEXT population, CRUD sync hooks, backfill script, index-first search with SQL fallback.
