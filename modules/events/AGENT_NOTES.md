# AGENT_NOTES.md - Events

## 1. Module Purpose
Manages scheduled events, meetings, and maintenance windows. Private events (no `shared_with_json` recipients) encrypt `title`, `description`, and `location` at rest with the user's vault key; shared events keep plaintext for recipients.

## 2. Key Tables
- **events** — main event data. Columns: `employee_id` (owner), `title_hash` (SHA-256 of plaintext title), `shared_with_json` (JSON array of employee ids), standard audit/metadata columns. `title`, `description`, and `location` are `TEXT`/`LONGTEXT` to hold ciphertext. Tenant unique-key audit skips `events` (duplicate titles allowed; encrypted titles — see `includes/database_sql_unique_audit.php`).
- **event_share_sessions** — temporary QR / 6-digit join snapshots (`payload_json`, `share_code`, `access_token`, `expires_at`). Private-data exempt (no `audit_logs`).

## 3. Required Relationships
- **events** → depends on **companies**.
- **events** → depends on **event_categories**.
- **events** → `employee_id` (owner) and **employees** via `assigned_to_employee_id`, plus metadata users `created_by`, `updated_by`, `deleted_by`.

## 4. Business Rules (Critical for Agents)
- **Date Validation**: `start_datetime` should generally be before `end_datetime`.
- **Visibility**: Owner (`employee_id`) or any employee listed in `shared_with_json` can view. Only the owner may edit, delete, or create share sessions.
- **Private vs shared**: Empty/null `shared_with_json` → encrypt `title`, `description`, `location` with `$_SESSION['vault_key']` via `events_prepare_event_fields_for_storage()`. Non-empty `shared_with_json` → plaintext for recipients.
- **Vault lock UI**: List/create and owner private edit/view require vault unlock (`events_vault_bootstrap.php` → `includes/itm_vault_unlock.php`, `events_ui_requires_vault_lock_screen()`). Shared events remain readable when vault is locked. Optional 6-digit TOTP when `employees.totp_enabled = 1`.
- **Search / sort / pagination**: In-memory via `events_query_events_for_list()` after hydrate (`events_row_matches_search()`, `events_compare_event_rows()`); list UI contract checks detect this helper like notes/bookmarks. Static audit: `scripts/check_fk_label_search_coverage.php` scans `events_vault_helpers.php`.
- **Add sample data:** empty-state uses visibility-scoped live rows (`events_count_visible_live_events()` — not raw `company_id` counts); seed via `itm_seed_insert_events_sample_rows()` stamps `employee_id` / `created_by` for the signed-in user and seeds `event_categories` when missing.
- **Master key change**: `itm_vault_reencrypt_events()` in `includes/itm_vault_master_key.php` (called from `user-config.php`).

## 5. UI Behavior Requirements
- **Standard flattened CRUD**: search across visible columns (`$displayFieldColumns` alias), sort (ASC/DESC ▲/▼), server-side pagination (`records_per_page`), bulk delete/clear when `$totalRows >= $perPage`, Export Excel/PDF, Import Excel via `table-tools.js`.
- **CSRF**: Form POST handlers use `cr_require_valid_csrf_token()`; JSON `import_excel_rows` validates via `itm_validate_csrf_token()`.
- **Hide `company_id`**, `employee_id`, `title_hash`, and `shared_with_json` from list/view forms (shared-with multi-select rendered separately).
- **Actions column**: `class="itm-actions-cell"` and `data-itm-actions-origin="1"` on one header + body cells; `itm-actions-wrap` holds ICS/share/CRUD controls. Settings → **Table actions position** (`table_actions_position` via `js/ui-layout.js`): `left_right` mirrors row action buttons on both sides of the table (single **Actions** header); `left` / `right` show one column only.
- **Import endpoint**: `data-itm-db-import-endpoint="index.php"` on the index list table.
- **ICS Export**: Visibility-scoped; hydrates rows before building VCALENDAR (single id or date range).
- **QR / code share (`join.php`):** owner-only; private events require vault unlock before `create_share_session`. Helpers: `events_share_helpers.php`, `events_vault_helpers.php`. Regression: `php scripts/verify_qr_share_modules.php`, `php scripts/verify_events_vault.php`.

## 6. API Actions (If Applicable)
- **import_excel_rows** — requires vault unlock; encrypts private rows on import.
- **create_share_session** — JSON POST `index.php?ajax_action=create_share_session` with `id`; owner-only; vault required for private events.

## 7. File Structure
- Standard CRUD wrappers + `index.php` (main logic).
- `events_vault_bootstrap.php`, `events_vault_helpers.php`, `events_share_helpers.php`, `join.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`. Owner `employee_id` is per-user within the tenant.

## 9. Audit Logging Requirements
- **`events` is private-data exempt** — no `trg_events_audit_*` triggers and no `audit_logs` rows for this table.
- **`event_categories`** remains auditable via database triggers.

## 10. Common Pitfalls
- **Soft-delete + audit meta:** list hides meta fields and filters `deleted_at IS NULL`; view shows audit stamps.
- Integrated into **calendar** — calendar must use `itm_events_visibility_sql()` and `events_hydrate_event_row()` for titles.
- Import and private create/edit require unlocked vault.
- Legacy seed/plaintext private rows: `events_resolve_private_text()` runs `legacy_plaintext_check` before `itm_decrypt()` to avoid openssl IV warnings on unencrypted titles/descriptions.
- Do not expose raw ciphertext or `title_hash` in the UI.

## 11. Examples of Safe Code Patterns

### Visibility-scoped SELECT
```php
$visSql = itm_events_visibility_sql('e');
$stmt = $conn->prepare("SELECT e.* FROM events e WHERE e.company_id = ? AND ($visSql)");
$stmt->bind_param('iii', $companyId, $employeeId, $employeeId);
```

### Encrypt before INSERT
```php
$prepared = events_prepare_event_fields_for_storage($title, $description, $location, $sharedWithJson);
if ($prepared === null) { /* vault locked */ }
```

## 12. Module Owner Notes (Optional)
Primary source of data for the Calendar module. Vault pattern mirrors `modules/notes/`.
