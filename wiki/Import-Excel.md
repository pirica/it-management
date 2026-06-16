# Import Excel (JSON save-to-database)

The **📥 Import Excel** tool in `js/table-tools.js` can save spreadsheet rows directly to MySQL. These rules mirror `AGENTS.md`.

## How it works

1. User imports a CSV/XLS/XLSX file from the module index table toolbar.
2. `table-tools.js` parses rows and POSTs JSON to the endpoint named on the table.
3. The module `index.php` handler calls `itm_handle_json_table_import()` in `config/config.php`.
4. The server responds with JSON (`ok`, `inserted`, `failed`, optional `warning`).

If the table has no endpoint attribute, import only updates the DOM (no database write).

## Required markup

Add on every module index table that should support database import:

```html
<table data-itm-db-import-endpoint="index.php" ...>
```

The attribute value is the POST target (usually `index.php` for that module).

## Required server handler

At the top of `modules/<module>/index.php` (after `config.php` is loaded), handle JSON import **before** normal page rendering:

```php
// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, '<table_name>', (int)($company_id ?? 0));
    }
}
```

Replace `<table_name>` with the module’s MySQL table (must pass `itm_is_safe_identifier()`).

### Reference implementations

- `modules/equipment/index.php`
- `modules/inventory_items/index.php`
- `modules/idfs/index.php`

## Request and response contract

**Client POST** (`table-tools.js`):

- `Content-Type: application/json`
- Body: `{ "import_excel_rows": [[...headers], [...row1], ...], "csrf_token": "<token>" }`
- CSRF token is read from a hidden `csrf_token` input on the page

**Server** (`itm_handle_json_table_import`):

- Validates CSRF, table name, and active `company_id` when the table has a `company_id` column
- Maps spreadsheet headers to column names (normalized labels)
- Returns JSON; on success the browser reloads the page

## Common failure

Generic alert: **“Import failed while saving to database.”**

Typical causes:

| Cause | Fix |
|-------|-----|
| Missing `data-itm-db-import-endpoint` on `<table>` | Add attribute |
| No JSON handler in `index.php` | Add early `import_excel_rows` block |
| Invalid CSRF | Ensure forms include `itm_get_csrf_token()` hidden input |
| No active company | User must have `company_id` set for tenant-scoped tables |
| Handler runs after HTML output | Call import handler at the start of `index.php` |

## Module standards (from AGENTS.md)

- Every standard module index table should expose the import endpoint attribute.
- Import rows must respect **company scoping** (`company_id` written automatically when the column exists).
- After import changes, run `php scripts/check_sql_injection_coverage.php`.
- Update `scripts/api.php` when import behavior or endpoints change.

## Pre-commit checklist

- [ ] Index `<table>` has `data-itm-db-import-endpoint="index.php"`
- [ ] `index.php` handles `import_excel_rows` via `itm_handle_json_table_import()`
- [ ] Import tested with a small valid spreadsheet for the active company
- [ ] CSRF token present on the index page
- [ ] SQL injection audit script run when PHP changed

## Related documentation

- [Foreign Keys & Display](Foreign-Keys) — imported FK columns should use valid related IDs or labels per module rules
- [Modules Overview](Modules)
- Repository source: `AGENTS.md` (Dynamic UI Configuration + JSON import endpoint guardrail)
