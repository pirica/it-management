# AGENT_NOTES.md - API v2 Gateway

## 1. Module Purpose
Partner-facing JSON REST gateway (Feature 6 / OpenAPI v2). Paid-tier integration keys with per-scope grants on `api_key_scopes`; PATH_INFO routing on `router.php` without `.htaccess` rewrites.

Separate from hotel distribution (`modules/hotel_booking_api/`) and from Free-tier session APIs documented in `scripts/api.php`.

## 2. Key Tables
- **ui_configuration** — integration `api_key`, tier, rate-limit counters (shared with Settings API Access).
- **api_key_scopes** — granted scope slugs per `ui_configuration_id` + `company_id`.
- **tickets**, **equipment** — MVP resources (tenant-scoped reads/writes).

## 3. Required Relationships
- **api_key_scopes** → **ui_configuration** (`ui_configuration_id`, CASCADE delete).
- **api_key_scopes** → **companies** (`company_id`, CASCADE delete).
- Handlers scope queries by session `company_id` bootstrapped from key owner.

## 4. Business Rules (Critical for Agents)
- **Auth:** paid tier + active `X-API-Key` only; Free tier rejected (`itm_api_tier_requires_api_key`).
- **Scopes:** fixed catalog in `itm_api_v2_scope_catalog()`; no arbitrary slugs from clients.
- **Default scopes:** read-only on key generate (`tickets.read`, `equipment.read`).
- **RBAC:** after scope check, `itm_user_has_role_module_permission()` for key owner's role.
- **Rate limit:** `itm_api_consume_rate_limit()` immediately after key lookup.
- **Envelope:** `{ ok, data }` / `{ ok, error, code }` JSON only.

## 5. UI Behavior Requirements
- No employee UI in this folder — configuration in **Settings → API Access** (scope checkboxes, OpenAPI link).
- OpenAPI spec is public: [scripts/openapi.php?format=json](http://localhost/it-management/scripts/openapi.php?format=json).

## 6. API Actions (If Applicable)
| Method | PATH_INFO | Scope |
|--------|-----------|-------|
| GET | `/probe` | valid paid key |
| GET/POST/PATCH | `/tickets`, `/tickets/{id}` | `tickets.read` / `tickets.write` |
| GET/POST/PATCH | `/equipment`, `/equipment/{id}` | `equipment.read` / `equipment.write` |

## 7. File Structure
- **router.php** — defines `ITM_API_V2`, loads config, calls `itm_api_v2_dispatch()`.
- **index.php** / **index.html** — directory listing guard.

## 8. Multi-Tenant Rules
- All handler SQL uses `company_id` from key owner; never accept client `company_id`.

## 9. Audit Logging Requirements
- **api_key_scopes** — `trg_api_key_scopes_audit_*` in `db/03_triggers.sql`.
- Ticket/equipment mutations follow existing table audit triggers when rows change.

## 10. Common Pitfalls
- **PATH_INFO:** document `router.php/tickets`, not pretty `/api/v2/*` URLs.
- **Do not** call `itm_enforce_module_access_or_exit()` for this slug — router uses `ITM_API_V2` skip login; module access gate is N/A for key-only entry.
- **Handler duplication:** keep JSON contracts in `includes/itm_api_v2_handlers/`; do not proxy to HTML `index.php` or `import_excel_rows`.

## 11. Examples of Safe Code Patterns

### Probe (curl)
```bash
curl -sS -H "X-API-Key: YOUR_KEY" \
  "http://localhost/it-management/modules/api_v2/router.php/probe"
```

## 12. Module Owner Notes (Optional)
Canonical doc: `docs/API_V2.md`. Regression: `php scripts/verify_api_v2.php`.
