# AGENT_NOTES.md - Bulk Import

---

## 1. Module Purpose

This module provides a centralized interface for importing Assets (Equipment) and Employees from CSV and Excel (XLSX/XLS) files. It leverages the centralized system handler for data normalization, foreign key resolution, and multi-tenant scoping.

---

## 2. Key Tables

- **equipment** — target table for Asset imports
- **employees** — target table for Employee imports
- **modules_registry** — used to register the import module
- **company_module_access** — manages access to the import module per company

---

## 3. Required Relationships

- Imports into **equipment** link to **manufacturers**, **equipment_types**, and **equipment_statuses**.
- Imports into **employees** link to **departments**, **employee_positions**, **employee_roles**, **access_levels**, and **employee_statuses**.

---

## 4. Business Rules (Critical for Agents)

- **Admin Only:** This module requires administrative privileges (`itm_require_admin`) as it performs bulk data operations on sensitive tables.
- **Multi-tenancy:** All imports are strictly scoped to the active `company_id`.
- **Automatic Lookups:** The centralized handler (`itm_handle_json_table_import`) automatically creates missing lookup values (e.g., new Manufacturers or Departments) when they don't exist for the tenant.
- **CSRF Protection:** All POST requests must include a valid CSRF token.

---

## 5. UI Behavior Requirements

- **Dual-Card Layout:** Separate cards for Assets and Employees import.
- **Template Downloads:** Provides links to download professional Excel templates.
- **AJAX Import:** Both CSV and XLSX files are parsed in the browser (via `xlsx.full.min.js`) and sent as JSON to the server.
- **Real-time Feedback:** Processing status and success/error messages are displayed via JavaScript and standard alerts.

---

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php`; handles the bulk insertion/update of records via `itm_handle_json_table_import`.

---

## 7. File Structure

- **index.php** — main dashboard for imports; handles both the UI and the JSON import endpoint.
- **asset_template.xlsx** — Excel template for Assets.
- **employee_template.xlsx** — Excel template for Employees.

---

## 8. Multi-Tenant Rules

- All data insertion and lookup resolution is strictly filtered by `company_id` from the session.

---

## 9. Audit Logging Requirements

- Handled by database triggers on the target tables (`equipment`, `employees`).
- The centralized handler sets appropriate MySQL session variables for actor context.

---

## 10. Common Pitfalls

- **Direct CSV Handling:** Procedural CSV parsing in PHP was removed in favor of unified AJAX/JSON processing. Do not re-introduce it.
- **Permissions:** Ensure `itm_require_admin` is called at the top of the entry file.
- **File Size:** Large imports may hit PHP `memory_limit` or `max_execution_time` if processed synchronously; the AJAX approach helps mitigate UI blocking but server-side limits still apply.

---

## 11. Examples of Safe Code Patterns

### Calling the Centralized Import Handler

```php
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, $tableName, (int)($company_id ?? 0));
    }
}
```

---

## 12. Module Owner Notes (Optional)

- This module relies on `js/vendor/xlsx.full.min.js` for client-side parsing.
- Verification is performed via end-to-end Playwright tests (e.g., `verify_import_v7.js`).
