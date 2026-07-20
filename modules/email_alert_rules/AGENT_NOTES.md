# AGENT_NOTES.md - Email Alert Rules

---

## 1. Module Purpose

Email Alert Rules manages per-company automated notification settings for the Email Management area. Rules control whether expiry and reminder alerts are enabled, how many days before an event they fire, and which recipient emails receive them.

---

## 2. Key Tables

- **email_alert_rules** — per-company alert toggles (`rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`).

---

## 3. Required Relationships

- **email_alert_rules** → **companies** via `company_id` (`ON DELETE CASCADE`).
- Alert dispatch is consumed by `scripts/run_email_alert_rules.php` and the parent Email Management module.

---

## 4. Business Rules (Critical for Agents)

- Email Alert Rules is a sub-feature of **Email Management**. Server-side CRUD permission checks must use the parent module slug **`emails`**, not `email_alert_rules`, because `db/` registers Email Management in `modules_registry` as `emails`.
- Rules are tenant-scoped by `company_id`; do not expose or edit `company_id` in the UI.
- `rule_slug` identifies the automation rule and should remain stable for the alert runner.

---

## 5. UI Behavior Requirements

- Standard flattened CRUD in `index.php` with wrappers (`create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`).
- Search, sort, pagination, bulk delete, import/export controls, and Actions-cell markers follow the standard CRUD pattern.
- Forms use `cr_require_valid_csrf_token()` for POST handling and include the shared CSRF token.
- Email Management also has a bespoke parent UI in `modules/emails/`; keep copy and behaviour aligned with the Alert Rules tab there.

---

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php` through the standard CRUD import flow.

---

## 7. File Structure

- **index.php** — list, create, edit, view, delete, bulk actions, and import handler depending on `crud_action`.
- **create.php** — wrapper to `index.php` create flow.
- **edit.php** — wrapper to `index.php` edit flow.
- **delete.php** — wrapper to `index.php` delete flow.
- **view.php** — wrapper to `index.php` detail flow.
- **list_all.php** — wrapper to `index.php` list-all flow.

---

## 8. Multi-Tenant Rules

- All row reads and writes are scoped by the active session `company_id`.
- `company_id` is hidden from list, detail, and form views.

---

## 9. Audit Logging Requirements

- Database triggers `trg_email_alert_rules_audit_insert`, `trg_email_alert_rules_audit_update`, and `trg_email_alert_rules_audit_delete` write to `audit_logs` on DML.
- Actor context comes from `@app_employee_id`, `@app_company_id`, and related session variables set by `config/config.php`.

---

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]

- Do not change CRUD RBAC to `$crud_table` for this module; use the parent `emails` permission scope so Email Management role permissions continue to cover Alert Rules. [Cursor-Valid]
- Do not document a standalone sidebar permission unless `modules_registry` is intentionally changed to add `email_alert_rules`. [Cursor-Valid]
- Do not remove `rule_slug` values used by scheduled alert runners without updating the runner logic and seeds. [Cursor-Valid]

---

## 11. Examples of Safe Code Patterns

### Safe SELECT

```php
$stmt = $conn->prepare('SELECT * FROM email_alert_rules WHERE company_id = ? AND id = ?');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
```

### Safe UPDATE

```php
$stmt = $conn->prepare('UPDATE email_alert_rules SET enabled = ?, days_before = ?, notify_emails = ? WHERE company_id = ? AND id = ?');
$stmt->bind_param('iisii', $enabled, $daysBefore, $notifyEmails, $companyId, $id);
$stmt->execute();
```

---

## 12. Module Owner Notes (Optional)

Parent module notes: `modules/emails/AGENT_NOTES.md`. Regression: `php scripts/verify_emails_module.php` when DB services are available.
