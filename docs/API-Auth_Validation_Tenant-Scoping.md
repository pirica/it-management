# API Security Audit Report: Authentication, Validation, and Tenant Scoping

This report reviews the IT Management System's API handlers and controller endpoints for security best practices regarding authentication, authorization, multi-tenant scoping, and request validation.

## 1. Executive Summary

The system follows a procedural PHP architecture where API endpoints are implemented as standalone scripts, internal AJAX handlers, or embedded within module `index.php` files. While session-based authentication and CSRF protection are widely applied, several critical vulnerabilities were confirmed through targeted testing. Key concerns include privilege escalation in generic APIs, potential remote code execution (RCE) in file management, and inconsistent request validation.

Analysis of `api-examples/` and the documentation in `scripts/api.php` confirms a heavy reliance on session cookies and manual scraping of HTML for data retrieval in the absence of comprehensive "read" APIs.

## 2. Flagged Endpoints & Findings

### 2.1 Generic Select Options API (`modules/select_options_api.php`)

*   **Description:** Provides a generic endpoint for creating new reference records (options) on-the-fly.
*   **Key Find:** **CONFIRMED PRIVILEGE ESCALATION.** A regular user can create records in sensitive tables like `users`, including creating new Admin users by supplying `role_id` and `access_level_id` in the `extra_fields` JSON payload.
*   **Trusting User-Supplied Identifiers:** The script trusts the `table`, `id_col`, and `label_col` parameters from the POST request. While it checks for "safe identifiers" (regex), it does not restrict the list of allowed tables.
*   **Weak Validation:** `extra_fields` are accepted without schema-based runtime validation.
*   **Recommendation:** Implement an allow-list of tables permitted for "Quick Add". Block sensitive tables (users, roles, permissions). Validate `extra_fields` against a predefined schema per table.

### 2.2 Explorer API (`modules/explorer/api.php`)

*   **Description:** Handles file and folder operations.
*   **Key Find:** **VERIFIED PATH TRAVERSAL PROTECTION.** Tests for `../../` in paths were correctly blocked by `get_full_path()`.
*   **Authenticated RCE:** (Known Vulnerability) The `upload` action allows uploading files to the `files/` directory. While it blocks some extensions, the protection is inconsistent with other upload directories.
*   **Multi-tenant Data Leak:** (Known Vulnerability) ZIP generation has potential for leaks if scoping is bypassed.
*   **Recommendation:** Move all uploaded files outside the web root or strictly enforce non-executable permissions via server configuration. Hardened path validation is required for ZIP generation.

### 2.3 JSON Import Endpoints (Shared & Module-Specific)

*   **Description:** Endpoints (often in `modules/*/index.php`) that accept `import_excel_rows` in a JSON body.
*   **Key Find:** **CONFIRMED WEAK DATA VALIDATION.** Payloads with invalid data types (e.g., string for a decimal price field) are accepted and result in `NULL` or default values in the database without returning an error to the client.
*   **Trusting User-Supplied IDs:** Some import handlers allow updating existing records by ID.
*   **Inconsistent Status Codes:** Error responses often return 200 OK with an `ok: false` field, which is misleading for automated tools.
*   **Recommendation:** Centralize and harden the import logic. Ensure every `UPDATE` or `DELETE` operation includes a `company_id = ?` clause derived strictly from the session.

### 2.4 Specialized AJAX Handlers (Switch Ports, Rack Planner, Org Chart)

*   **Description:** High-interaction modules use specialized handlers like `includes/update_port.php`.
*   **Key Find:** **MISLEADING SUCCESS RESPONSES.** Some handlers (e.g., `notes/index.php?ajax_action=single_delete`) correctly apply session-based scoping but return `{"ok":true}` even when no record is found or when the action is blocked by tenant isolation.
*   **Implicit Scoping:** These scripts often rely on the global `$company_id` from `config.php`.
*   **Recommendation:** Encapsulate complex multi-table synchronizations into transaction-aware functions. Ensure return values accurately reflect the outcome (e.g., return 404 if no record was updated).

### 2.5 Documented Internal & Security APIs (`scripts/api.php`, `scripts/test_sql_injection.php`)

*   **Description:** Maintenance and security tools are documented as APIs for shared use.
*   **Issues:**
    *   **Exposure of Sensitive Logic:** `test_sql_injection.php` explicitly demonstrates internal SQL injection signatures. Its exposure should be strictly limited.
    *   **RBAC Gaps:** Documented tools like `module_browser_qa_runner.php` and `compare_database_sql_modules.php` perform sensitive operations. If not properly guarded by Admin role checks, they could be abused by regular users.
*   **Recommendation:** Explicitly verify Admin role membership for all scripts in the `scripts/` directory and any documented maintenance APIs. Disable security test scripts in production.

## 3. Security Test Results

Targeted tests were executed using sample data and established session contexts to verify identified vulnerabilities.

### 3.1 Test Case Matrix

| Endpoint | Test Action | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- |
| `select_options_api.php` | Regular user creating Admin | Blocked | **Success (Admin created)** | 🔴 FAIL |
| `equipment/view.php` | Accessing other company asset | Redirect/404 | 302 Redirect to Login | 🟢 PASS |
| `notes/index.php` (AJAX) | Deleting other company note | Blocked | Returned `{"ok":true}` (No deletion) | 🟡 WARN |
| `catalogs/index.php` (Import) | Importing invalid price type | 400 Bad Request | **Success (Inserted as NULL)** | 🔴 FAIL |
| `catalogs/index.php` (Import) | Supplying `company_id` in row | Blocked/Ignored | **Ignored (Session ID used)** | 🟢 PASS |
| `explorer/api.php` | Path traversal (`../../`) | Blocked | `{"items":[]}` / `{"content":""}` | 🟢 PASS |
| `test_sql_injection.php` | Tautology payload (`' OR '1'='1`) | Detected & Blocked | **Blocked (422 Unprocessable Entity)** | 🟢 PASS |

### 3.2 Evidence of Vulnerabilities

#### Privilege Escalation in Select Options API
A regular user (ID: 2) successfully created a new Admin user by targeting the `users` table via `modules/select_options_api.php`:
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
# Result: A new Admin user was created in the database.
```

#### Weak Data Validation in JSON Import
The Catalog import handler accepted a non-numeric string for the `price` field (DECIMAL) and successfully inserted the row with a `NULL` price instead of rejecting the invalid input:
```bash
# Payload sent
POST /modules/catalogs/index.php
{
  "csrf_token": "valid_token",
  "import_excel_rows": [["Model", "Price"], ["Test Catalog Item", "invalid-price"]]
}
# Response: {"ok":true,"inserted":1}
# Database: id=94, model='Test Catalog Item', price=NULL
```

#### Misleading Success in Multi-tenant Deletion
Deleting a note belonging to another user/company returned a success status even though no database modification occurred:
```bash
# Payload sent as RegularUser targeting Admin note (ID: 1)
POST /modules/notes/index.php?ajax_action=single_delete
{ "csrf_token": "valid_token", "id": 1 }
# Response: {"ok":true}
# Database: Note ID 1 remains unchanged (correct scoping, but misleading response).
```

## 4. Architectural Observations (from api-examples & scripts/api.php)

1.  **Reliance on HTML Scraping:** `api-examples/employees_singleview.php` and `api-examples/tickets_listall_open.php` demonstrate that developers are forced to use Regex or DOM parsing on HTML views because structured JSON "Read" APIs are missing. This is brittle and exposes internal UI structure.
2.  **Implicit Tenant Scoping:** The documentation in `scripts/api.php` reinforces that most endpoints assume a valid session but does not explicitly detail the authorization checks (role/level) performed within each endpoint.
3.  **Generic Table APIs:** Generic APIs (like `select_options_api.php`) that take a `table` name as input are inherently risky and should be replaced with specific, validated endpoints.

## 5. Recommendations for Remediation

1.  **Strict Table Allow-lists:** For any generic API (like Select Options), implement a strict allow-list of permitted tables and columns.
2.  **Schema-Based Validation:** Introduce a structured way to validate incoming JSON payloads against expected schemas.
3.  **Standardize Response Contract:** Define a consistent JSON response format and appropriate HTTP status codes (400, 401, 403, 404, 500) for all API endpoints.
4.  **Enforce Mandatory Tenant Scoping:** Ensure all database queries involving multi-tenant tables include a `company_id` filter derived *only* from the session.
5.  **Implement Rate Limiting:** Add application-level throttling for sensitive or resource-intensive API actions.
6.  **Introduce Structured Read APIs:** Develop JSON-based endpoints for retrieving record details to replace current HTML scraping patterns.
7.  **RBAC for Maintenance Tools:** Ensure all documented maintenance and security tools require the Admin role and are disabled in production environments.
