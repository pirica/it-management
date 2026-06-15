# API Security Audit Report: Authentication, Validation, and Tenant Scoping

## 1. Executive Summary

This report reviews the IT Management System's API handlers and controller endpoints for security best practices related to authentication, authorization, multi-tenant scoping, and request validation.

The system follows a procedural PHP architecture where API endpoints are implemented as standalone scripts, internal AJAX handlers, or embedded within module `index.php` files. Session-based authentication and CSRF protection are widely applied; however, several critical vulnerabilities and security weaknesses were identified.

Key concerns include:

• Privilege escalation in generic APIs.
• Potential authenticated remote code execution (RCE) through file uploads.
• Weak and inconsistent request validation.
• Inconsistent API response handling.
• Risks associated with generic table-based APIs.

Analysis of `api-examples/` and documentation in `scripts/api.php` confirms a heavy reliance on session cookies and HTML scraping for data retrieval due to the absence of comprehensive JSON-based read APIs.

---

## 2. Flagged Endpoints & Findings

### 2.1 Generic Select Options API (`modules/select_options_api.php`)

**Description**

Provides a generic endpoint for creating reference records ("Quick Add" functionality).

**Findings**

**Confirmed Privilege Escalation**

A regular user can create records in sensitive tables such as `users`, including creating new administrator accounts by supplying `role_id` and `access_level_id` values through the `extra_fields` JSON payload.

**Trusting User-Supplied Identifiers**

The endpoint accepts `table`, `id_col`, and `label_col` parameters directly from the client. Although basic identifier validation is performed using regular expressions, there is no allow-list restricting which database tables may be accessed.

**Weak Validation**

`extra_fields` are accepted without schema-based validation, allowing arbitrary field injection.

**Recommendation**

• Implement a strict allow-list of permitted tables and columns.
• Block sensitive tables such as `users`, `roles`, and `permissions`.
• Validate `extra_fields` against predefined schemas.
• Separate record creation from option retrieval operations.

---

### 2.2 Explorer API (`modules/explorer/api.php`)

**Description**

Handles file and folder management operations.

**Findings**

**Verified Path Traversal Protection**

Testing confirmed that attempts to access paths such as `../../` are blocked by path normalization routines.

**Authenticated RCE Risk**

The upload functionality permits authenticated users to upload files into the `files/` directory. Extension filtering exists but is not consistently enforced across all upload locations.

**Potential Multi-Tenant Data Leakage**

ZIP archive generation may expose data across tenants if path validation or scoping controls are bypassed.

**Recommendation**

• Store uploaded files outside the web root.
• Enforce non-executable permissions on upload directories.
• Strengthen ZIP generation path validation and tenant isolation checks.

---

### 2.3 JSON Import Endpoints

**Description**

Module-specific endpoints that process `import_excel_rows` JSON payloads.

**Findings**

**Confirmed Weak Data Validation**

Invalid data types are accepted and silently converted to `NULL` or default values rather than generating validation errors.

**Trusting User-Supplied Record IDs**

Some import handlers allow updates to existing records through client-supplied identifiers. If tenant scoping is not consistently enforced, this creates an IDOR risk.

**Inconsistent Status Codes**

Many failures return HTTP 200 responses containing `{"ok": false}` rather than using appropriate HTTP error codes.

**Recommendation**

• Centralize import processing logic.
• Implement schema-based validation.
• Reject invalid payloads with HTTP 400 responses.
• Ensure all `UPDATE` and `DELETE` queries include mandatory tenant scoping using session-derived `company_id`.

---

### 2.4 Specialized AJAX Handlers

Examples include switch port management, rack planner, organizational chart, and note management handlers.

**Findings**

**Misleading Success Responses**

Some endpoints return success responses even when no record was modified due to tenant isolation or missing records.

**Complex Side Effects**

Certain handlers synchronize data across multiple tables, increasing the risk of inconsistent authorization and tenant-scoping enforcement.

**Implicit Scoping**

Many scripts rely on the global `$company_id` value rather than applying explicit tenant constraints within each query.

**Recommendation**

• Use transaction-aware service functions for multi-table updates.
• Implement consistent validation schemas.
• Return accurate status codes and responses when operations affect zero records.

---

### 2.5 Internal Maintenance and Security APIs (`scripts/`)

**Description**

Administrative and diagnostic scripts documented for internal use.

**Findings**

**Exposure of Sensitive Logic**

Security testing utilities expose attack patterns and internal implementation details.

**Potential RBAC Gaps**

Maintenance scripts perform sensitive operations and could be abused if administrative access controls are incomplete.

**Recommendation**

• Restrict all maintenance tools to administrators.
• Disable testing and security assessment utilities in production environments.
• Audit all scripts under the `scripts/` directory for proper RBAC enforcement.

---

## 3. Security Test Results

| Endpoint                      | Test Action                         | Expected Result  | Actual Result                     | Status  |
| ----------------------------- | ----------------------------------- | ---------------- | --------------------------------- | ------- |
| `select_options_api.php`      | Regular user creating administrator | Blocked          | Administrator created             | 🔴 FAIL |
| `equipment/view.php`          | Access another company's asset      | Redirect/404     | Redirect to login                 | 🟢 PASS |
| `notes/index.php` (AJAX)      | Delete another company's note       | Blocked          | Returned success without deletion | 🟡 WARN |
| `catalogs/index.php` (Import) | Import invalid price type           | Validation error | Inserted as NULL                  | 🔴 FAIL |
| `catalogs/index.php` (Import) | Supply custom `company_id`          | Ignored          | Session company used              | 🟢 PASS |
| `explorer/api.php`            | Path traversal (`../../`)           | Blocked          | Access prevented                  | 🟢 PASS |

Status Legend

🟢 PASS – Security control works as expected.

🟡 WARN – No direct security bypass observed, but behavior is misleading, inconsistent, or could complicate monitoring and auditing.

🔴 FAIL – Confirmed security weakness, vulnerability, or control failure requiring remediation.
---

## 4. Architectural Observations

### Reliance on HTML Scraping

Examples in `api-examples/` demonstrate that developers frequently parse HTML responses using regular expressions or DOM processing because structured JSON read APIs are unavailable. This approach is fragile and tightly couples integrations to UI implementation details.

### Implicit Tenant Scoping

The system generally derives `company_id` from the session, but authorization rules are not consistently visible or enforced at the endpoint level.

### Generic Table APIs

APIs that accept table names and column names directly from user input introduce significant attack surface and should be replaced with purpose-built endpoints.

### Validation Strategy

Validation is largely procedural, duplicated across modules, and lacks a centralized schema-driven framework.

### Error Handling

HTTP status codes are used inconsistently, making automation and monitoring more difficult.

### Rate Limiting

No centralized rate-limiting controls were identified. Bulk imports, ZIP generation, and similar resource-intensive operations may be susceptible to denial-of-service attacks.

---

## 5. Remediation Recommendations

### High Priority

1. Implement strict allow-lists for all generic APIs.
2. Block access to sensitive tables through generic endpoints.
3. Enforce mandatory tenant scoping on all multi-tenant queries.
4. Move uploaded files outside the web root and disable execution.
5. Apply consistent RBAC controls to administrative and maintenance scripts.

### Medium Priority

6. Introduce schema-based request validation.
7. Standardize API response formats and HTTP status codes.
8. Refactor complex multi-table operations into transaction-aware services.
9. Return accurate success and failure responses.

### Long-Term Improvements

10. Develop structured JSON read APIs to eliminate HTML scraping.
11. Introduce centralized rate limiting and request throttling.
12. Establish a unified validation and authorization framework across all modules.

---

## Conclusion

The application demonstrates generally effective session-based authentication and tenant isolation practices; however, several high-impact vulnerabilities remain, most notably privilege escalation through generic APIs, weak validation within import functionality, and file upload risks.

Addressing these issues through stricter authorization controls, schema-based validation, mandatory tenant scoping, and purpose-built APIs will significantly improve the platform's security posture and reduce the likelihood of privilege escalation, data leakage, and unauthorized access.


## Prompt




Require API Auth, Validation, and Tenant Scoping

Agent options Review API handlers and controller endpoints for missing authentication, authorization, tenant scoping, and request validation.


- Don't delete any files
- Before start read last report:
 docs/API-Auth_Validation_Tenant-Scoping.md
- AGENTS.md 
- scripts/scripts.php
- scripts/SCRIPTS.md
- scripts/api.php
- api-examples/*

-- Update report docs/API-Auth_Validation_Tenant-Scoping.md
Add a "Test Results" section documenting the specific payloads and outcomes of the tests.
Provide concrete evidence for the identified vulnerabilities.


Flag new or modified endpoints that:

Accept request bodies without runtime validation, such as Zod schemas, DTOs, or framework-native validators. Trust user-supplied identifiers for tenant, organization, or account access instead of deriving access from the authenticated session. Return sensitive internal errors or use inconsistent HTTP status codes for 400, 401, 403, 404, and 500 cases. Mix reads and writes in a way that makes side effects hard to reason about. Add public or high-volume endpoints without rate limiting or abuse controls.

