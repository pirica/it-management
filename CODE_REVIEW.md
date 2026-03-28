# Code Review

Date: 2026-03-28
Scope reviewed:
- `modules/_shared/crud_page.php`
- `modules/_shared/select_options_api.php`
- `js/select-add-option.js`

## Summary
This review focused on security and data-integrity behavior in the generic CRUD and dynamic select-option add flow. I found three high-priority issues:

1. State-changing actions are exposed without CSRF protection.
2. Destructive delete action uses GET.
3. Numeric coercion can silently overwrite invalid input with `0`.

## Findings

### 1) Missing CSRF protections on write operations (High)
**Where:**
- `modules/_shared/crud_page.php` form submissions for create/edit.
- `modules/_shared/crud_page.php` delete path.
- `modules/_shared/select_options_api.php` POST endpoint for creating new related values.

**Why this matters:**
Authenticated users can be tricked into submitting cross-site requests from another origin, resulting in unauthorized record create/update/delete.

**Recommendation:**
- Introduce a per-session CSRF token and validate it on all state-changing endpoints.
- Reject missing/invalid tokens with 403 responses.
- Include token validation in both HTML form posts and XHR/fetch endpoints.

### 2) Delete operation is triggered by GET request (High)
**Where:**
- `modules/_shared/crud_page.php` delete handler reads `$_GET['id']` and executes `DELETE`.

**Why this matters:**
GET should be safe/idempotent. Triggering deletion by URL creates accidental-delete risk (e.g., crawlers/prefetchers) and compounds CSRF exposure.

**Recommendation:**
- Require POST/DELETE for deletion.
- Replace the delete anchor with a small form using POST and CSRF token.
- Return 405 for non-POST delete attempts.

### 3) Silent numeric coercion to 0 on invalid input (Medium)
**Where:**
- `modules/_shared/crud_page.php` in create/edit value normalization:
  - numeric-looking DB types are set with `(string)(0 + $value)`.

**Why this matters:**
Invalid numeric input (e.g., `abc`) is silently converted to `0`, which can corrupt data and make user mistakes hard to detect.

**Recommendation:**
- Validate numeric inputs with explicit checks (`filter_var`, `is_numeric`, min/max/range validation by column type).
- Add user-facing validation errors instead of coercion.
- Prefer prepared statements with bound numeric types for writes.

## Optional hardening ideas
- Add server-side allowlists for which FK tables/columns may be manipulated by `select_options_api.php`, instead of trusting client-provided table metadata.
- Centralize DB write helpers for consistent validation/escaping/authorization checks.
