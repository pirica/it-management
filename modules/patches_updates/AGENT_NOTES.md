# AGENT_NOTES.md - Patches & Updates

## 1. Module Purpose
Tracks software patches, security updates, and system upgrades across equipment.

## 2. Key Tables
- **patches_updates** — main patch records.

## 3. Required Relationships
- **patches_updates** → depends on **companies**.
- **patches_updates** → depends on **equipment**.
- **patches_updates** → depends on **patches_updates_level**.
- **patches_updates** → depends on **patches_updates_status**.

## 4. Business Rules (Critical for Agents)
- **Release Tracking**: Monitors the lifecycle of a patch from "Planned" to "Installed".
- **Severity Levels**: Categorizes patches by importance (e.g., "Critical", "Optional").

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()` on the request body token. Forms include hidden `csrf_token` from `cr_get_csrf_token()`.
- **Hide `company_id`** from list, view, and create/edit forms.
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on Actions header and body cells.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **`active` field**: list/view use `badge-success` / `badge-danger` (no emoji); create/edit use `itm-checkbox-control` with ✅/❌.

- **Photo Upload**: Supports screenshots of patch confirmation or errors. Stored under `tickets_photos/` with names `patch_update_{id}_{time}_{rand}.{ext}`. Extension is derived from detected MIME via `cr_upload_extension_from_mime()` (never from the client filename). MIME must be in `ALLOWED_TYPES`; size must be ≤ `MAX_FILE_SIZE` (5MB).

## 6. API Actions (If Applicable)
- **import_excel_rows** — JSON POST to `index.php`; bulk import from 📥 Import Excel (`table-tools.js` save-to-database flow).

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Database triggers `trg_patches_updates_audit_insert`, `trg_patches_updates_audit_update`, `trg_patches_updates_audit_delete` on `patches_updates` in `database.sql` always write to `audit_logs` on INSERT/UPDATE/DELETE (unconditional DB triggers; not gated by `enable_audit_logs`).

## 10. Common Pitfalls
- Do not delete rows still referenced by inbound FKs — reassign or detach dependents for the active `company_id` first. [Cursor-Valid]
- Storing photo files using the client-supplied filename extension (e.g. `.php` on an image MIME). [Cursor-Fixed]
- Links to **equipment** and patch status/level lookups. [Cursor-Valid]
- Respect tenant unique constraints; duplicates fail at the database layer. [Cursor-Valid]
- Scope every SELECT/INSERT/UPDATE/DELETE by `company_id`; never expose `company_id` in the UI. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM patches_updates WHERE company_id = ? AND equipment_id = ?");
$stmt->bind_param("ii", $companyId, $equipmentId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO patches_updates (company_id, hostname, active) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $companyId, $hostname, $active);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Essential for security compliance and vulnerability management.
