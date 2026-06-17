# AGENT_NOTES.md - Manufacturers

## 1. Module Purpose
Lookup table for equipment and inventory manufacturers (e.g., "Dell", "Cisco", "HP"). Also serves as the **canonical flattened CRUD template** for simple reference tables (dynamic schema from `information_schema`).

## 2. Key Tables
- **manufacturers** — stores manufacturer names and status.

## 3. Required Relationships
- **manufacturers** → depends on **companies**.
- **manufacturers** → referenced by **equipment**, **catalogs**, **inventory_items**.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Manufacturer name must be unique per company.
- **Template contract:** Only `modules/manufacturers/` is allowed to be the live manufacturers CRUD. Other modules must **not** use `require __DIR__ . '/../manufacturers/…'` delegates.
- **New DB tables without a bespoke module:** `itm_auto_create_module_scaffold()` in `includes/ui_config.php` copies this module's PHP files into `modules/{table}/` with `$crud_table` / `$crud_title` substitutions (`itm_materialize_manufacturers_crud_module_files()`). Sidebar discovery labels those entries with **⚠️** (`itm_sidebar_auto_scaffolded_module_emoji()`).
- **Refreshing a materialized module** after template fixes: `itm_materialize_manufacturers_crud_module_files($slug, true)` (overwrite). Never materialize over `manufacturers` itself.

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: POST handlers use `itm_require_post_csrf()`; forms include hidden `csrf_token`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- `index.php` — main flattened CRUD (list + POST handlers).
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — entry wrappers or full screens per action.
- Materialized siblings (for example `modules/note_labels/`, `modules/modules_registry/`) mirror this layout locally.

## 8. Multi-Tenant Rules
- Scoped by `company_id`; hide `company_id` from UI.

## 9. Audit Logging Requirements
- Database triggers `trg_manufacturers_audit_insert`, `trg_manufacturers_audit_update`, `trg_manufacturers_audit_delete` on `manufacturers` in `database.sql` write to `audit_logs` when `enable_audit_logs` is enabled.

## 10. Common Pitfalls
- Do not add cross-module `require '../manufacturers/'` stubs — QA cleanup removes legacy delegate folders via `itm_remove_manufacturers_template_scaffold_module_dirs()`. Run `php scripts/check_manufacturers_delegate_requires.php` to catch new violations.
- When changing shared CRUD behaviour here, re-materialize dependent modules or patch them explicitly.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM manufacturers WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO manufacturers (company_id, name, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $name, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Central lookup for asset branding; canonical source for the manufacturers CRUD materialization helpers in `includes/ui_config.php`.
