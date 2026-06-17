# API Security Audit Report: Authentication, Validation, and Tenant Scoping

This report reviews the IT Management System's API handlers and controller endpoints for security best practices regarding authentication, authorization, multi-tenant scoping, and request validation.

## 1. Executive Summary

The system follows a procedural PHP architecture where API endpoints are implemented as standalone scripts, internal AJAX handlers, or embedded within module `index.php` files. While session-based authentication and CSRF protection are widely applied, several critical vulnerabilities were confirmed through targeted testing. Key concerns include privilege escalation in generic APIs, potential remote code execution (RCE) in file management, and inconsistent request validation.

Analysis of `api-examples/` and the documentation in `scripts/api.php` confirms a heavy reliance on session cookies and manual scraping of HTML for data retrieval in the absence of comprehensive "read" APIs.

## 2. Flagged Endpoints & Findings

### 2.1 Generic Select Options API (`modules/select_options_api.php`)

*   **Description:** Provides a generic endpoint for creating new reference records (options) on-the-fly.
*   **Key Find:** **REMEDIATED (table whitelist).** Quick-add inserts are restricted to lookup tables in `includes/itm_select_options_policy.php`. Sensitive tables (`users`, `user_roles`, `role_module_permissions`, `access_levels`, and related identity/RBAC tables) are blocked; privilege fields such as `role_id` and `access_level_id` are stripped from `extra_fields`.
*   **Trusting User-Supplied Identifiers:** The script still accepts `id_col` and `label_col` from POST, but `table` must pass the server-side allow-list before any insert runs.
*   **Weak Validation:** `extra_fields` are accepted without schema-based runtime validation.
*   **Recommendation:** Implement an allow-list of tables permitted for "Quick Add". Block sensitive tables (users, roles, permissions). Validate `extra_fields` against a predefined schema per table.

### 2.2 Explorer API (`modules/explorer/api.php`)

*   **Description:** Handles file and folder operations.
*   **Key Find:** **VERIFIED PATH TRAVERSAL PROTECTION.** Tests for `../../` in paths were correctly blocked by `get_full_path()`.
*   **Authenticated RCE:** **REMEDIATED** — executable extensions blocked on upload; `deny_http` hardening on every `files/` segment via `itm_ensure_files_storage_directory()`. Regression: `php scripts/verify_explorer_rce_htaccess.php`.
*   **Multi-tenant Data Leak:** **REMEDIATED** — `downloadZip` blocks `Private`/`Departments` roots; scoped paths via `get_full_path()`. Regression: `php scripts/verify_explorer_zip_leak.php`.
*   **Recommendation:** Keep upload hardening helpers authoritative; serve tenant files only through `modules/explorer/file.php`.

### 2.3 JSON Import Endpoints (Shared & Module-Specific)

*   **Description:** Endpoints (often in `modules/*/index.php`) that accept `import_excel_rows` in a JSON body.
*   **Key Find:** **CONFIRMED WEAK DATA VALIDATION (remediated for numeric, date/datetime, and enum columns).** Invalid types increment `failed`, set `ok:false`, and return HTTP 400 when no rows are inserted. Regression: `php scripts/verify_json_import_validation.php`.
*   **Trusting User-Supplied IDs:** Some import handlers allow updating existing records by ID; updates require matching tenant `company_id`.
*   **Status codes:** Validation failures use HTTP 400 with `ok:false` when the import produces no inserts/updates; CSRF and malformed JSON already return 400; sensitive-table imports return 403.
*   **Recommendation:** Centralize and harden the import logic. Ensure every `UPDATE` or `DELETE` operation includes a `company_id = ?` clause derived strictly from the session.

### 2.4 Specialized AJAX Handlers (Switch Ports, Rack Planner, Org Chart)

*   **Description:** High-interaction modules use specialized handlers like `includes/update_port.php`.
*   **Key Find:** **REMEDIATED (affected_rows contract).** Notes, Org Chart hierarchy updates, Rack Planner auto-save, and switch-port updates return HTTP 404 when tenant-scoped mutations match zero rows. CSRF failures return HTTP 403; validation errors return HTTP 400/409. Helpers: `itm_notes_json_mutation_response()`, `itm_api_json_response()` / `itm_api_mutation_requires_rows()`. Regressions: `php scripts/verify_notes_ajax_contract.php`, `php scripts/verify_json_import_validation.php`.
*   **Implicit Scoping:** These scripts often rely on the global `$company_id` from `config.php`.
*   **Recommendation:** Encapsulate complex multi-table synchronizations into transaction-aware functions. Ensure return values accurately reflect the outcome (e.g., return 404 if no record was updated).

### 2.5 Documented Internal & Security APIs (`scripts/api.php`, `scripts/test_sql_injection.php`)

*   **Description:** Maintenance and security tools are documented as APIs for shared use.
*   **Issues:**
    *   **Exposure of Sensitive Logic:** `test_sql_injection.php` explicitly demonstrates internal SQL injection signatures. Browser access requires Admin (`itm_enforce_maintenance_script_admin_browser()`); disable in production.
    *   **RBAC (remediated for catalogued tools):** `module_browser_qa_runner.php`, `compare_database_sql_modules.php`, and `test_sql_injection.php` call `itm_enforce_maintenance_script_admin_browser()` for browser sessions. CLI runners remain available for smoke/MBQA. Regression: `php scripts/verify_maintenance_scripts_rbac.php`.
*   **Recommendation:** Keep Admin browser gates on maintenance tools; disable security test scripts in production.

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

### 3.2 Evidence of Vulnerabilities

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
3.  **Generic Table APIs:** Generic APIs (like `select_options_api.php`) that take a `table` name as input are inherently risky and should be replaced with specific, validated endpoints.

## 5. Recommendations for Remediation

1.  **Strict Table Allow-lists:** For any generic API (like Select Options), implement a strict allow-list of permitted tables and columns.
2.  **Schema-Based Validation:** Introduce a structured way to validate incoming JSON payloads against expected schemas.
3.  **Standardize Response Contract (partial — Notes + JSON import):** Use `{"ok":bool,...}` with HTTP status codes that match the outcome. Implemented patterns:

    | Condition | HTTP | JSON |
    | :--- | :--- | :--- |
    | CSRF invalid / missing | 403 | `ok:false` |
    | Malformed or invalid input | 400 | `ok:false` + error detail |
    | Auth / tenant block (sensitive import table) | 403 | `ok:false` |
    | Scoped mutation matched zero rows | 404 | `ok:false` |
    | Duplicate conflict (e.g. tag exists) | 409 | `ok:false` |
    | Import validation failed (no rows saved) | 400 | `ok:false`, `failed` ≥ 1 |

    Helpers: `itm_notes_json_mutation_response()` (Notes AJAX); `itm_api_json_response()` / `itm_api_mutation_requires_rows()` (Org Chart, Rack Planner, switch ports); `itm_handle_json_table_import()` (module imports). Regressions: `php scripts/verify_notes_ajax_contract.php`, `php scripts/verify_json_import_validation.php`.
4.  **Enforce Mandatory Tenant Scoping:** Ensure all database queries involving multi-tenant tables include a `company_id` filter derived *only* from the session.
5.  **Rate Limiting (implemented):** Per-user API tiers and hourly quotas live on `ui_configuration`; enforcement in `includes/itm_api_rate_limit.php` (`itm_api_enforce_rate_limit_or_exit()`). Probe: `GET scripts/api.php?rate_limit=1`. Regressions: `php scripts/apitest_tier_free.php`, `php scripts/apitest_tier_basic.php`.
6.  **Introduce Structured Read APIs:** Develop JSON-based endpoints for retrieving record details to replace current HTML scraping patterns.
7.  **RBAC for Maintenance Tools (partial — catalogued scripts):** Browser access to `module_browser_qa_runner.php`, `compare_database_sql_modules.php`, and `test_sql_injection.php` requires Admin via `includes/itm_maintenance_script_admin_gate.php`. Regression: `php scripts/verify_maintenance_scripts_rbac.php`. Audit remaining documented scripts in future sweeps; disable security test scripts in production.
