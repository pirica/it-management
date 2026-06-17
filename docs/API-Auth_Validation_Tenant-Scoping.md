# API Security Audit Report: Authentication, Validation, and Tenant Scoping

This report reviews the IT Management System's API handlers and controller endpoints for security best practices regarding authentication, authorization, multi-tenant scoping, and request validation.

## 1. Executive Summary

The system follows a procedural PHP architecture where API endpoints are implemented as standalone scripts, internal AJAX handlers, or embedded within module `index.php` files. Session-based authentication and CSRF protection are widely applied.

The June 2026 review confirmed several critical and high-severity issues (privilege escalation via generic APIs, Explorer upload/RCE and ZIP leak, JSON import tenant scoping, misleading AJAX success responses, and maintenance-tool exposure). **All active findings in the June 2026 catalog are remediated or deferred** — see [`VULNERABILITY_SUMMARY.md`](VULNERABILITY_SUMMARY.md) and [`app---flagged-vulnerabilities.json`](app---flagged-vulnerabilities.json). One item (`reset_git_history.php`) remains **deferred** as accepted BETA risk until production.

Remaining work is **follow-up hardening and architecture** (structured read APIs, per-table schema validation for Select Options `extra_fields`, optional RBAC sweep on additional maintenance scripts) — not open vulnerability findings.

Analysis of `api-examples/` and the documentation in `scripts/api.php` confirms a heavy reliance on session cookies and manual scraping of HTML for data retrieval in the absence of comprehensive "read" APIs.

## 2. Flagged Endpoints & Findings

### 2.1 Generic Select Options API (`modules/select_options_api.php`)

*   **Description:** Provides a generic endpoint for creating new reference records (options) on-the-fly.
*   **Key Find:** **REMEDIATED (table whitelist).** Quick-add inserts are restricted to lookup tables in `includes/itm_select_options_policy.php`. Sensitive tables (`users`, `user_roles`, `role_module_permissions`, `access_levels`, and related identity/RBAC tables) are blocked; privilege fields such as `role_id` and `access_level_id` are stripped from `extra_fields`. Regression: `php scripts/verify_select_options_escalation.php`.
*   **Trusting User-Supplied Identifiers:** The script still accepts `id_col` and `label_col` from POST, but `table` must pass the server-side allow-list before any insert runs.
*   **Remaining follow-up:** `extra_fields` are not yet validated against a per-table schema at runtime.
*   **Recommendation (follow-up):** Add schema-based validation for `extra_fields` on allowed tables.

### 2.2 Explorer API (`modules/explorer/api.php`)

*   **Description:** Handles file and folder operations.
*   **Key Find:** **VERIFIED PATH TRAVERSAL PROTECTION.** Tests for `../../` in paths were correctly blocked by `get_full_path()`.
*   **Authenticated RCE:** **REMEDIATED** — executable extensions blocked on upload; `deny_http` hardening on every `files/` segment via `itm_ensure_files_storage_directory()`. Regression: `php scripts/verify_explorer_rce_htaccess.php`.
*   **Multi-tenant Data Leak:** **REMEDIATED** — `downloadZip` blocks `Private`/`Departments` roots; scoped paths via `get_full_path()`. Regression: `php scripts/verify_explorer_zip_leak.php`.
*   **Recommendation:** Keep upload hardening helpers authoritative; serve tenant files only through `modules/explorer/file.php`.

### 2.3 JSON Import Endpoints (Shared & Module-Specific)

*   **Description:** Endpoints (often in `modules/*/index.php`) that accept `import_excel_rows` in a JSON body.
*   **Key Find:** **REMEDIATED (typed column validation + tenant scoping).** Invalid numeric, date/datetime, and enum values increment `failed`, set `ok:false`, and return HTTP 400 when no rows are inserted. Session `company_id` is enforced on insert; updates require matching `id` and `company_id`; sensitive tables require admin. Regression: `php scripts/verify_json_import_validation.php`.
*   **Status codes:** Validation failures use HTTP 400 with `ok:false` when the import produces no inserts/updates; CSRF and malformed JSON return 400; sensitive-table imports return 403.
*   **Remaining follow-up:** General per-table schema validation beyond typed columns (e.g. free-text constraints).
*   **Recommendation (follow-up):** Extend centralized import validation with table-specific schemas where needed.

### 2.4 Specialized AJAX Handlers (Switch Ports, Rack Planner, Org Chart)

*   **Description:** High-interaction modules use specialized handlers like `includes/update_port.php`.
*   **Key Find:** **REMEDIATED (affected_rows contract).** Notes, Org Chart hierarchy updates, Rack Planner auto-save, and switch-port updates return HTTP 404 when tenant-scoped mutations match zero rows. CSRF failures return HTTP 403; validation errors return HTTP 400/409. Helpers: `itm_notes_json_mutation_response()`, `itm_api_json_response()` / `itm_api_mutation_requires_rows()`. Regressions: `php scripts/verify_notes_ajax_contract.php`, `php scripts/verify_json_import_validation.php`.
*   **Implicit Scoping:** These scripts rely on the global `$company_id` from `config.php`.
*   **Recommendation (follow-up):** Encapsulate complex multi-table synchronizations (e.g. IDF/switch-port parity) in transaction-aware functions where not already wrapped.

### 2.5 Documented Internal & Security APIs (`scripts/api.php`, `scripts/test_sql_injection.php`)

*   **Description:** Maintenance and security tools are documented as APIs for shared use.
*   **Status:**
    *   **Exposure of Sensitive Logic:** `test_sql_injection.php` demonstrates internal SQL injection signatures. **REMEDIATED (browser Admin gate).** Browser access requires Admin via `itm_enforce_maintenance_script_admin_browser()`. Disable in production.
    *   **RBAC:** **REMEDIATED (catalogued tools).** `module_browser_qa_runner.php`, `compare_database_sql_modules.php`, and `test_sql_injection.php` call the Admin browser gate. CLI runners remain available for smoke/MBQA. Regression: `php scripts/verify_maintenance_scripts_rbac.php`.
*   **Recommendation (follow-up):** Audit remaining documented maintenance scripts for the same browser Admin gate; disable security test scripts in production.

## 3. Security Test Results

Targeted tests were executed using sample data and established session contexts to verify identified vulnerabilities.

### 3.1 Test Case Matrix

| Endpoint | Test Action | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- |
| `select_options_api.php` | Regular user creating Admin | Blocked | Blocked (HTTP 403, no row inserted) | 🟢 PASS |
| `equipment/view.php` | Accessing other company asset | Redirect/404 | 302 Redirect to Login | 🟢 PASS |
| `notes/index.php` (AJAX) | Deleting other company note | Blocked | HTTP 404, `{"ok":false}` (no deletion) | 🟢 PASS |
| `catalogs/index.php` (Import) | Importing invalid price type | 400 Bad Request | **Rejected (`ok:false`, `failed` ≥ 1, no row)** | 🟢 PASS |
| `catalogs/index.php` (Import) | Supplying `company_id` in row | Blocked/Ignored | **Ignored (Session ID used)** | 🟢 PASS |
| `explorer/api.php` | Path traversal (`../../`) | Blocked | `{"items":[]}` / `{"content":""}` | 🟢 PASS |
| `test_sql_injection.php` | Tautology payload (`' OR '1'='1`) | Detected & Blocked | **Blocked (422 Unprocessable Entity)** | 🟢 PASS |

### 3.2 Historical evidence (remediated findings)

#### Privilege Escalation in Select Options API (remediated)
A regular user attempting to create an Admin via the `users` table receives HTTP 403 and no database row is inserted. Verification: `php scripts/verify_select_options_escalation.php`.

Previously vulnerable payload (now blocked):
```bash
# Payload sent as RegularUser
POST /modules/select_options_api.php
{
  "csrf_token": "valid_token",
  "table": "users",
  "id_col": "id",
  "label_col": "username",
  "new_value": "EvilAdmin",
  "extra_fields": "{\"email\":\"evil@evil.com\",\"password\":\"evil\",\"role_id\":1,\"access_level_id\":1}"
}
# Result: Request rejected; no Admin user is created.
```

#### Weak Data Validation in JSON Import (remediated)
Invalid decimal strings in catalog import are rejected; no row is inserted and the handler returns `ok:false` with HTTP 400 when nothing was imported:
```bash
# Payload sent
POST /modules/catalogs/index.php
{
  "csrf_token": "valid_token",
  "import_excel_rows": [["Model", "Price"], ["Test Catalog Item", "invalid-price"]]
}
# Response: HTTP 400, {"ok":false,"inserted":0,"failed":1,...}
# Database: no new row for the invalid import line
```

#### Misleading Success in Multi-tenant Deletion (remediated)
Deleting a note belonging to another user/company returns HTTP 404 with `ok:false` while the note remains unchanged:
```bash
# Payload sent as RegularUser targeting another user's note
POST /modules/notes/index.php?ajax_action=single_delete
{ "csrf_token": "valid_token", "id": 1 }
# Response: HTTP 404, {"ok":false,"error":"Record not found or not permitted"}
# Database: Note remains unchanged (correct scoping)
```

## 4. Architectural Observations (from api-examples & scripts/api.php)

1.  **Reliance on HTML Scraping:** `api-examples/employees_singleview.php` and `api-examples/tickets_listall_open.php` demonstrate that developers are forced to use Regex or DOM parsing on HTML views because structured JSON "Read" APIs are missing. This is brittle and exposes internal UI structure.
2.  **Implicit Tenant Scoping:** The documentation in `scripts/api.php` reinforces that most endpoints assume a valid session but does not explicitly detail the authorization checks (role/level) performed within each endpoint.
3.  **Generic Table APIs:** Generic APIs (like `select_options_api.php`) that take a `table` name as input are inherently risky; table allow-listing is implemented, but specific validated endpoints remain a long-term goal.

## 5. Recommendations

### 5.1 Implemented (June 2026 review)

1.  **Strict Table Allow-lists:** Select Options quick-add uses `includes/itm_select_options_policy.php`. Regression: `php scripts/verify_select_options_escalation.php`.
2.  **JSON Import Hardening:** Typed validation and tenant scoping in `itm_handle_json_table_import()`. Regression: `php scripts/verify_json_import_validation.php`.
3.  **Standardized Response Contract:** Use `{"ok":bool,...}` or `{"success":bool,...}` with HTTP status codes that match the outcome:

    | Condition | HTTP | JSON |
    | :--- | :--- | :--- |
    | CSRF invalid / missing | 403 | `ok:false` / `success:false` |
    | Malformed or invalid input | 400 | `ok:false` + error detail |
    | Auth / tenant block (sensitive import table) | 403 | `ok:false` |
    | Scoped mutation matched zero rows | 404 | `ok:false` / `success:false` |
    | Duplicate conflict (e.g. tag exists) | 409 | `ok:false` |
    | Import validation failed (no rows saved) | 400 | `ok:false`, `failed` ≥ 1 |

    Helpers: `itm_notes_json_mutation_response()` (Notes); `itm_api_json_response()` / `itm_api_mutation_requires_rows()` (Org Chart, Rack Planner, switch ports); `itm_handle_json_table_import()` (imports). Regressions: `php scripts/verify_notes_ajax_contract.php`, `php scripts/verify_json_import_validation.php`.
4.  **Mandatory Tenant Scoping on JSON Import:** Session-derived `company_id` on insert; updates require matching tenant row. Sensitive tables admin-only.
5.  **Rate Limiting:** Per-user API tiers on `ui_configuration`; enforcement in `includes/itm_api_rate_limit.php`. Probe: `GET scripts/api.php?rate_limit=1`. Regressions: `php scripts/apitest_tier_free.php`, `php scripts/apitest_tier_basic.php`.
6.  **Maintenance Script RBAC (catalogued tools):** Browser Admin gate via `includes/itm_maintenance_script_admin_gate.php` on `module_browser_qa_runner.php`, `compare_database_sql_modules.php`, and `test_sql_injection.php`. Regression: `php scripts/verify_maintenance_scripts_rbac.php`.

### 5.2 Remaining follow-ups (not open vulnerability findings)

1.  **Schema-Based Validation:** Per-table schemas for Select Options `extra_fields` and broader import payloads.
2.  **Structured Read APIs:** JSON endpoints to replace HTML scraping in `api-examples/`.
3.  **Maintenance Script RBAC (extended sweep):** Apply the same browser Admin gate to any remaining sensitive documented scripts.
4.  **Production git-reset hardening:** Admin + CSRF on `reset_git_history.php` when leaving BETA — see [`vulnerability_report_git_reset.md`](vulnerability_report_git_reset.md).
