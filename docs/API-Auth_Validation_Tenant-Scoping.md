# API Security Audit Report: Authentication, Validation, and Tenant Scoping

This report reviews the IT Management System's API handlers and controller endpoints for security best practices regarding authentication, authorization, multi-tenant scoping, and request validation.

## 1. Executive Summary

The system follows a procedural PHP architecture where API endpoints are often implemented as standalone scripts or embedded within module `index.php` files. While session-based authentication and CSRF protection are widely applied via `config/config.php` and helper functions, several critical vulnerabilities and areas for improvement were identified. Key concerns include privilege escalation in generic APIs, potential remote code execution (RCE) in file management, and inconsistent request validation.

Analysis of `api-examples/` further confirms these patterns, demonstrating a heavy reliance on session cookies and manual scraping of HTML for data retrieval in the absence of comprehensive "read" APIs.

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
    *   **Authenticated RCE:** (Known Vulnerability) The `upload` action allows uploading files to the `files/` directory. While it blocks some extensions, the protection is inconsistent with other upload directories (like `floor_plans/` which uses `.htaccess` to disable script execution).
    *   **Multi-tenant Data Leak:** (Known Vulnerability) The `downloadZip` functionality has been flagged for potential multi-tenant leaks if path traversal or improper scoping is exploited.
    *   **Sensitive Internal Errors:** Some error cases return generic messages, but the reliance on `basename()` and manual path concatenation is error-prone.
*   **Recommendation:** Move all uploaded files outside the web root or strictly enforce non-executable permissions via server configuration (e.g., `.htaccess` for Apache). Hardened path validation is required for ZIP generation.

### 2.3 JSON Import Endpoints (Shared & Module-Specific)

*   **Description:** Endpoints (often in `modules/*/index.php`) that accept `import_excel_rows` in a JSON body.
*   **Issues:**
    *   **Missing Runtime Validation:** While `itm_handle_json_table_import` performs some type checking based on `DESCRIBE`, it lacks robust validation (like Zod schemas or DTOs) to enforce business logic rules before DB insertion.
    *   **Trusting User-Supplied IDs:** Some import handlers (e.g., `modules/notes/index.php`) allow updating existing records by supplying an `id`. If tenant scoping is not strictly enforced in the `UPDATE` query, this could lead to IDOR.
    *   **Inconsistent Status Codes:** Error responses sometimes use 400, sometimes 403, and sometimes 200 with an `ok: false` field in the JSON.
*   **Recommendation:** Centralize and harden the import logic. Ensure every `UPDATE` or `DELETE` operation in import handlers includes a `company_id = ?` clause derived from the session.

### 2.4 Contacts Inline Edit API (`modules/contacts/api/inline_edit.php`)

*   **Description:** Handles inline updates for contact fields.
*   **Issues:**
    *   **Returning Sensitive Internal Errors:** In case of failure, it returns `mysqli_error($conn)`, which can leak database schema details or internal query structure.
    *   **Missing Runtime Validation:** The `value` parameter is not validated against the field type (e.g., ensuring an email field contains a valid email format).
*   **Recommendation:** Replace `mysqli_error` with generic error messages. Add field-specific validation before executing the update.

### 2.5 IDF API Endpoints (`modules/idfs/api/*.php`)

*   **Description:** A set of specialized endpoints for IDF rack management (`port_update.php`, `position_save.php`, etc.).
*   **Issues:**
    *   **Complex Side Effects:** Endpoints like `port_update.php` synchronize data across multiple tables (`idf_ports`, `switch_ports`, `equipment`, `idf_links`). While they generally use session `company_id`, the complex logic makes it difficult to ensure consistent scoping across all affected rows.
    *   **Ad-hoc Validation:** Validation is performed using basic functions like `is_numeric` or `ctype_digit` scattered throughout the scripts.
*   **Recommendation:** Encapsulate complex multi-table synchronizations into well-tested, transaction-aware functions. Use a more structured approach to request validation.

## 3. General Observations & Patterns

1.  **Tenant Scoping:** The system generally derives `company_id` from the session (`$_SESSION['company_id']`), which is a good practice. However, generic APIs (like `select_options_api.php`) sometimes take a `company_scoped` flag from the client, which determines whether the session's `company_id` is applied. This logic should be inverted: if a table has a `company_id` column, scoping should be mandatory.
2.  **Validation:** There is a lack of a centralized validation framework. Most validation is procedural and repetitive. `api-examples/employees_singleview.php` and `api-examples/tickets_listall_open.php` highlight that the system lacks structured JSON "read" APIs, forcing clients to use regex or DOM parsing on HTML views for data extraction, which is brittle and insecure.
3.  **Error Handling:** HTTP status codes are inconsistently used. 200 OK is often returned even when the application logic fails (with an `error` field in JSON), which can be misleading for API clients.
4.  **Rate Limiting:** No centralized rate limiting was found for API endpoints. High-volume endpoints or those performing expensive operations (like ZIP generation or bulk imports) are vulnerable to denial-of-service or abuse.

## 4. Recommendations for Remediation

1.  **Strict Table Allow-lists:** For any generic API that interacts with multiple tables, implement a strict allow-list of permitted tables and columns.
2.  **Schema-Based Validation:** Introduce a structured way to validate incoming JSON payloads against the expected schema (e.g., using a DTO-like pattern or a centralized validation utility).
3.  **Standardize Response Contract:** Define a consistent JSON response format and appropriate HTTP status codes for all API endpoints:
    *   `400 Bad Request`: Validation errors, missing fields.
    *   `401 Unauthorized`: Missing or invalid session.
    *   `403 Forbidden`: Valid session but insufficient permissions or CSRF failure.
    *   `404 Not Found`: Resource not found within tenant scope.
    *   `500 Internal Server Error`: Database or server failures (mask internal details).
4.  **Enforce Mandatory Tenant Scoping:** Ensure that all database queries involving multi-tenant tables automatically include a `company_id` filter derived *only* from the session, never from the request body or parameters.
5.  **Implement Rate Limiting:** Add application-level throttling for sensitive or resource-intensive API actions.
6.  **Introduce Structured Read APIs:** Develop JSON-based endpoints for retrieving record details and lists to replace the current reliance on HTML scraping demonstrated in `api-examples/`.
