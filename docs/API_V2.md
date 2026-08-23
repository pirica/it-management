# API v2 — Partner JSON REST Gateway

Canonical documentation for the **OpenAPI v2 Gateway** (Feature 6): versioned JSON REST under `modules/api_v2/router.php`, scoped integration keys on paid tiers, and a machine-readable OpenAPI 3.0 spec.

This surface is **separate** from:

- **Hotel booking distribution** — `modules/hotel_booking_api/api.php` (channel keys on `hotel_booking_distribution_channels`)
- **Legacy session/AJAX endpoints** — documented in [scripts/api.php](http://localhost/it-management/scripts/api.php?run=1) (Admin session)
- **Free-tier session probe** — `GET scripts/api.php?rate_limit=1`

---

## Base URL and routing

No `.htaccess` rewrite. Apache passes the suffix as `PATH_INFO` on the router file:

| Item | Value |
|------|--------|
| Entry | `modules/api_v2/router.php` |
| Local base | `http://localhost/it-management/modules/api_v2/router.php` |
| Example list | `GET .../router.php/tickets` |
| Example item | `GET .../router.php/tickets/42` |
| Probe | `GET .../router.php/probe` |

Partners concatenate **`server.url + path`** from the OpenAPI document (path is the PATH_INFO suffix, e.g. `/tickets`).

---

## Authentication

| Rule | Detail |
|------|--------|
| Header | `X-API-Key: <key>` (or query/body `api_key`) |
| Key storage | `ui_configuration.api_key` per `company_id` + `employee_id` |
| Tier | **Paid only** (Basic, Pro, Enterprise). Free tier is rejected with HTTP 403. |
| Session | No employee login. Router sets `$_SESSION['company_id']` and `$_SESSION['employee_id']` from the key owner for RBAC only. |
| Rate limit | Same rolling-hour counters as `includes/itm_api_rate_limit.php` (`itm_api_consume_rate_limit`) |

Manage keys and scopes: **Settings → API Access** ([modules/settings/index.php](http://localhost/it-management/modules/settings/index.php)) — paid tiers only.

---

## Scopes

Granted rows live in **`api_key_scopes`** (`scope_slug` per `ui_configuration_id`).

| Scope | Allows |
|-------|--------|
| `tickets.read` | `GET /tickets`, `GET /tickets/{id}` |
| `tickets.write` | `POST /tickets`, `PATCH /tickets/{id}` |
| `equipment.read` | `GET /equipment`, `GET /equipment/{id}` |
| `equipment.write` | `POST /equipment`, `PATCH /equipment/{id}` |

**Default on key generate:** read-only (`tickets.read`, `equipment.read`). Enable write scopes in Settings when integrations need create/update.

**Probe** (`GET /probe`) requires any valid paid key; returns granted scopes and route metadata.

RBAC: after scope check, the router enforces `role_module_permissions` for the key owner (`can_view`, `can_create`, `can_edit`).

---

## Response envelope

```json
{"ok": true, "data": { ... }}
```

Errors:

```json
{"ok": false, "error": "Missing required scope: tickets.write.", "code": 403}
```

`Content-Type: application/json; charset=utf-8`

Common HTTP codes: `401` invalid/missing key, `403` scope or RBAC, `404` not found, `422` validation, `429` rate limit.

---

## MVP routes

| Method | Path | Scope | Notes |
|--------|------|-------|-------|
| GET | `/probe` | (valid paid key) | Metadata + scopes |
| GET | `/tickets` | `tickets.read` | Query: `search`, `limit` (1–100) |
| GET | `/tickets/{id}` | `tickets.read` | FK labels in JSON |
| POST | `/tickets` | `tickets.write` | JSON body; `title` required |
| PATCH | `/tickets/{id}` | `tickets.write` | Partial update |
| GET | `/equipment` | `equipment.read` | Query: `search`, `limit` |
| GET | `/equipment/{id}` | `equipment.read` | |
| POST | `/equipment` | `equipment.write` | `name`, `equipment_type_id`, `status_id` required |
| PATCH | `/equipment/{id}` | `equipment.write` | Partial update |

**Excluded by design:** vault/private modules, hotel distribution, CSRF/session HTML import paths.

---

## OpenAPI 3.0

Public spec (no secrets):

- Browser: [scripts/openapi.php?format=json](http://localhost/it-management/scripts/openapi.php?format=json) — open in a new tab
- CLI: `php scripts/openapi.php`

Built from `itm_api_v2_route_registry()` in `includes/itm_api_v2_openapi.php`.

---

## Examples

| Script | Purpose |
|--------|---------|
| `api-examples/api_v2_probe.php` | `GET /probe` with `X-API-Key` |
| `api-examples/api_v2_tickets_list.php` | `GET /tickets` list |
| `api-examples/api_v2_ticket_get.php` | `GET /tickets/{id}` |
| `api-examples/api_v2_ticket_create.php` | `POST /tickets` (`tickets.write`) |
| `api-examples/api_v2_ticket_update.php` | `PATCH /tickets/{id}` (`tickets.write`) |
| `api-examples/api_v2_equipment_list.php` | `GET /equipment` list |
| `api-examples/api_v2_equipment_get.php` | `GET /equipment/{id}` |
| `api-examples/api_v2_equipment_create.php` | `POST /equipment` (`equipment.write`) |
| `api-examples/api_v2_equipment_update.php` | `PATCH /equipment/{id}` (`equipment.write`) |

Set `ITM_API_V2_KEY` in the environment or edit the placeholder in each file. Optional record ids: `ITM_API_V2_TICKET_ID`, `ITM_API_V2_EQUIPMENT_ID`; equipment create: `ITM_API_V2_EQUIPMENT_TYPE_ID`, `ITM_API_V2_EQUIPMENT_STATUS_ID`.

---

## Regression

| Command | Purpose |
|---------|---------|
| `php scripts/verify_api_v2.php` | Table, routes, scopes, handlers, OpenAPI |
| [verify_api_v2.php?run=1](http://localhost/it-management/scripts/verify_api_v2.php?run=1) | Browser (Admin session) |

PHPUnit: `php scripts/run_tests.php --filter ApiV2`

---

## Schema

- **`api_key_scopes`** — migration `db/migrations/api_v2_scopes.sql`; mirrored in `db/01_schema.sql`; audit triggers in `db/03_triggers.sql`.

Apply on existing databases: `php scripts/migrate.php --apply` (or import migration SQL manually).

---

## Implementation map

| Area | Files |
|------|--------|
| Router | `modules/api_v2/router.php` |
| Core | `includes/itm_api_v2.php`, `includes/itm_api_v2_scopes.php` |
| Handlers | `includes/itm_api_v2_handlers/tickets.php`, `equipment.php` |
| OpenAPI | `includes/itm_api_v2_openapi.php`, `scripts/openapi.php` |
| Config bypass | `ITM_API_V2` in `config/config.php` (skip web login) |
