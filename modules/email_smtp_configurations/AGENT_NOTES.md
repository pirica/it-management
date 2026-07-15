# AGENT_NOTES.md - Email SMTP Configurations

---

## 1. Module Purpose

This module manages SMTP profiles used for outbound email delivery. It allows tenants to configure multiple SMTP configurations, with one designated as the default for the company.

---

## 2. Key Tables

- **email_smtp_configurations** — stores SMTP host, port, credentials, and security settings.

---

## 3. Required Relationships

- **email_smtp_configurations** → depends on **companies** (`company_id`, `ON DELETE CASCADE`)
- **email_smtp_configurations** → referenced by **emails** (`smtp_config_id`, `ON DELETE SET NULL`)

---

## 4. Business Rules (Critical for Agents)

- **Default Profile**: Each company should have exactly one profile with `is_default = 1`. The UI in `modules/emails/` handles clearing other defaults, but the CRUD module here may not enforce it automatically.
- **Encryption**: SMTP passwords MUST be stored encrypted using `itm_email_encrypt_password()`.
- **Active Status**: Profiles can be toggled as active/inactive.

---

## 5. UI Behavior Requirements

### Flattened CRUD (`modules/email_smtp_configurations/index.php`)

- **Search, Sort, Pagination**: Standard server-side implementation using `records_per_page`.
- **Bulk Actions**: Supports "Select to Delete" and "Clear Table" when `$totalRows >= $perPage`.
- **Column Display**: Hides `company_id` from list and forms.
- **Action Icons**: Uses `🔎` (View), `✏️` (Edit), and `🗑️` (Delete) with `.itm-actions-cell`.
- **Import**: Supports `📥 Import Excel` via `data-itm-db-import-endpoint="index.php"`.
- **Active Checkbox**: Uses the standard `itm-checkbox-control` pattern.
- **FK Labels**: Uses `itm_crud_fk_label_search_conditions` for searching across related tables (though this table primarily relates to `companies` which is hidden).

---

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php` for bulk importing configurations.

---

## 7. File Structure

- **index.php** — main list view and CRUD router
- **create.php** — creation form wrapper
- **edit.php** — edit form wrapper
- **view.php** — detail view wrapper
- **delete.php** — deletion handler
- **list_all.php** — alternate list view wrapper

---

## 8. Multi-Tenant Rules

- All queries and operations are strictly scoped by `company_id` from the session.

---

## 9. Audit Logging Requirements

- Database triggers: `trg_email_smtp_configurations_audit_insert|update|delete` automatically log changes to the `audit_logs` table.

---

## 10. Common Pitfalls

- **Password Encryption**: Ensure passwords are encrypted before storage; never store plain text. [Valid]-[2026-07-15]
- **Default Flag**: Manually setting `is_default` for multiple rows via SQL can lead to ambiguous delivery behavior. [Valid]-[2026-07-15]
- Critical for system communications. [Valid]-[2026-07-15]

---

## 11. Examples of Safe Code Patterns

### Safe SELECT

```php
$stmt = mysqli_prepare($conn, "SELECT * FROM email_smtp_configurations WHERE id = ? AND company_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
```

### Safe INSERT

```php
$stmt = mysqli_prepare($conn, "INSERT INTO email_smtp_configurations (company_id, config_name, smtp_host, smtp_port, is_default) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'issii', $company_id, $config_name, $smtp_host, $smtp_port, $is_default);
mysqli_stmt_execute($stmt);
```

---

## 12. Module Owner Notes (Optional)

Part of the larger Email Management system. See also `modules/emails/AGENT_NOTES.md`.
