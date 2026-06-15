# API Security Audit Report: Authentication, Validation, and Tenant Scoping

This report reviews the IT Management System's API handlers and controller endpoints for security best practices regarding authentication, authorization, multi-tenant scoping, and request validation.

## 1. Executive Summary

The system follows a procedural PHP architecture where API endpoints are implemented as standalone scripts, internal AJAX handlers, or embedded within module `index.php` files. While session-based authentication and CSRF protection are widely applied via `config/config.php` and helper functions, several critical vulnerabilities and areas for improvement were identified. Key concerns include privilege escalation in generic APIs, potential remote code execution (RCE) in file management, and inconsistent request validation.

Analysis of `api-examples/` and the documentation in `scripts/api.php` confirms a heavy reliance on session cookies and manual scraping of HTML for data retrieval in the absence of comprehensive "read" APIs.

## 2. Flagged Endpoints & Findings

### 2.1 Generic Select Options API (`modules/select_options_api.php`)

*   **Description:** Provides a generic endpoint for creating new reference records (options) on-the-fly.
*   **Issues:**
    *   **Privilege Escalation:** A regular user can create records in sensitive tables like `users`, including creating new Admin users by supplying `role_id` and `access_level_id` in the `extra_fields` JSON payload.
    *   **Trusting User-Supplied Identifiers:** The script trusts the `table`, `id_col`, and `label_col` parameters from the POST request. While it checks for "safe identifiers" (regex), it does not restrict the list of allowed tables.
    *   **Weak Validation:** `extra_fields` are accepted without schema-based runtime validation beyond basic type checks during insertion.
    *   **Mixed Reads and Writes:** The endpoint performs both record insertion and returns a refreshed list of options, making side effects harder to reason about in a single request.
*   **Recommendation:** Implement an allow-list of tables permitted for "Quick Add". Block sensitive tables (users, roles, permissions). Validate `extra_fields` against a predefined schema per table.

### 2.2 Explorer API (`modules/explorer/api.php`)

*   **Description:** Handles file and folder operations.
*   **Issues:**
    *   **Authenticated RCE:** (Known Vulnerability) The `upload` action allows uploading files to the `files/` directory. While it blocks some extensions, the protection is inconsistent with other upload directories.
    *   **Multi-tenant Data Leak:** (Known Vulnerability) The `downloadZip` functionality has been flagged for potential multi-tenant leaks if path traversal or improper scoping is exploited.
    *   **Sensitive Internal Errors:** Some error cases return generic messages, but the reliance on `basename()` and manual path concatenation is error-prone.
*   **Recommendation:** Move all uploaded files outside the web root or strictly enforce non-executable permissions via server configuration. Hardened path validation is required for ZIP generation.

### 2.3 JSON Import Endpoints (Shared & Module-Specific)

*   **Description:** Endpoints (often in `modules/*/index.php`) that accept `import_excel_rows` in a JSON body.
*   **Issues:**
    *   **Missing Runtime Validation:** While `itm_handle_json_table_import` performs basic type checking, it lacks robust validation (like Zod schemas or DTOs) to enforce business logic rules.
    *   **Trusting User-Supplied IDs:** Some import handlers allow updating existing records by supplying an `id`. If tenant scoping is not strictly enforced in the `UPDATE` query, this could lead to IDOR.
    *   **Inconsistent Status Codes:** Error responses often return 200 OK with an `ok: false` field, which is misleading for automated tools.
*   **Recommendation:** Centralize and harden the import logic. Ensure every `UPDATE` or `DELETE` operation includes a `company_id = ?` clause derived strictly from the session.

### 2.4 Specialized AJAX Handlers (Switch Ports, Rack Planner, Org Chart)

*   **Description:** High-interaction modules use specialized handlers like `includes/get_ports.php`, `includes/update_port.php`, and `modules/rack_planner/index.php`.
*   **Issues:**
    *   **Complex Side Effects:** Endpoints like `update_port.php` synchronize data across multiple tables (`switch_ports`, `idf_ports`, `equipment`). The complexity of these operations makes it difficult to verify consistent scoping and authorization across all affected records.
    *   **Implicit Scoping:** These scripts often rely on the global `$company_id` from `config.php`. While generally correct, the lack of explicit per-query scoping in some paths increases the risk of tenant leaks.
    *   **Ad-hoc Validation:** Validation is performed using scattered basic checks (`is_numeric`, `ctype_digit`) rather than a structured schema.
*   **Recommendation:** Encapsulate complex multi-table synchronizations into transaction-aware functions. Implement structural validation for complex JSON payloads (e.g., Rack Planner layout JSON).

### 2.5 Documented Internal & Security APIs (`scripts/api.php`, `scripts/test_sql_injection.php`)

*   **Description:** Maintenance and security tools are documented as APIs for shared use.
*   **Issues:**
    *   **Exposure of Sensitive Logic:** `test_sql_injection.php` explicitly demonstrates internal SQL injection signatures. Its exposure should be strictly limited.
    *   **RBAC Gaps:** Documented tools like `module_browser_qa_runner.php` and `compare_database_sql_modules.php` perform sensitive operations. If not properly guarded by Admin role checks, they could be abused by regular users.
*   **Recommendation:** Explicitly verify Admin role membership for all scripts in the `scripts/` directory and any documented maintenance APIs. Disable security test scripts in production.

## 3. General Observations & Patterns

1.  **Tenant Scoping:** The system generally derives `company_id` from the session, but some generic APIs take a `company_scoped` flag from the client. This should be inverted: if a table has a `company_id` column, scoping must be mandatory and derived solely from the session.
2.  **Validation:** There is a lack of a centralized validation framework. Most validation is procedural and repetitive. `api-examples/` confirm that the absence of structured JSON "read" APIs forces insecure and brittle HTML scraping for data retrieval.
3.  **Error Handling:** HTTP status codes are inconsistently used. Sensitive internal errors (e.g., `mysqli_error`) are occasionally returned to the client.
4.  **Rate Limiting:** No centralized rate limiting was found. Resource-intensive actions (ZIP generation, bulk imports) are vulnerable to DoS.

## 4. Recommendations for Remediation

1.  **Strict Table Allow-lists:** For any generic API (like Select Options), implement a strict allow-list of permitted tables and columns.
2.  **Schema-Based Validation:** Introduce a structured way to validate incoming JSON payloads against expected schemas.
3.  **Standardize Response Contract:** Define a consistent JSON response format and appropriate HTTP status codes (400, 401, 403, 404, 500) for all API endpoints.
4.  **Enforce Mandatory Tenant Scoping:** Ensure all database queries involving multi-tenant tables include a `company_id` filter derived *only* from the session.
5.  **Implement Rate Limiting:** Add application-level throttling for sensitive or resource-intensive API actions.
6.  **Introduce Structured Read APIs:** Develop JSON-based endpoints for retrieving record details to replace current HTML scraping patterns.
7.  **RBAC for Maintenance Tools:** Ensure all documented maintenance and security tools require the Admin role.
