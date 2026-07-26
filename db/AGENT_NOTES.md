# AGENT_NOTES.md - db/

## 1. Module Purpose
Canonical SQL schema, seed data, and audit triggers for the IT Management System.

## 4. Business Rules (Critical for Agents)
- **Canonical source:** edit `db/01_schema.sql` (DDL), `db/02_data.sql` (DML/seeds), and `db/03_triggers.sql` (triggers) directly — ship schema changes in those files first; mirror them in `db/migrations/` only for existing databases.
- **Import order:** `01_schema.sql` → `02_data.sql` → `03_triggers.sql` in **one MySQL session** (`bash scripts/import_database_split.sh`). Numeric prefix matches run order.
- **Boundaries:** DDL in `01_schema.sql`, DML in `02_data.sql`, triggers in `03_triggers.sql`.
- **Incremental migrations:** `db/migrations/{module}_{subject}.sql` — copy/paste `DROP TABLE IF EXISTS` + full `CREATE TABLE` from `db/01_schema.sql` (no `ALTER TABLE`, no `_new` staging). See `db/migrations/AGENT_NOTES.md`.
- **`employees` TOTP (vault 2FA):** `totp_secret` (`TEXT`, encrypted at rest in PHP via `itm_totp_encrypt_secret()`), `totp_enabled` (`TINYINT(1) NOT NULL DEFAULT 0`) immediately after `vault_key_hash` in `01_schema.sql`. Live migration: `db/migrations/employee_totp.sql` (destructive `DROP` + `CREATE` — back up or re-import `02_data.sql` after apply).
- **`employee_roles.sidebar_show`:** `TINYINT(1) NOT NULL DEFAULT 1` on `employee_roles` — per-role sidebar visibility override edited in `modules/roles_permissions/` and flattened `modules/employee_roles/`. Live migration: `db/migrations/employee_roles_sidebar_show.sql` (destructive `DROP` + `CREATE` — back up or re-import `02_data.sql` after apply).
- **QR share (private-data exempt):** unified `share_sessions` (`module_slug`, `record_id`, optional `scope_path` / `scope_path_hash` for Explorer). Company enable/disable matrix: `company_module_share` + `modules/share_modules/`. Capable slug list: `itm_qr_share_capable_module_slugs()` in `includes/itm_qr_share.php` (32 slugs — 9 vault/explorer + 23 CRUD record modules). `company_module_share` seeded in `db/02_data.sql` for those slugs only; live DB backfill: `db/migrations/company_module_share_capable_seed.sql` or `php scripts/apply_new_company_module_share_capable_seed.php --apply`. No rows in `02_data.sql` for `share_sessions`; no audit triggers on `share_sessions` in `03_triggers.sql`. Canonical agent doc: `docs/CRUD_RECORD_SHARE.md`. Live migration from per-module `*_share_sessions` tables: `db/migrations/share_sessions_unified.sql`.
- **`companies` audit triggers:** `trg_companies_audit_*` use `COALESCE(@app_company_id, NEW.id|OLD.id, 0)` for `audit_logs.company_id` (not bare `COALESCE(@app_company_id, 0)`). Live fix: `db/migrations/companies_audit_triggers.sql`.
- **`employee_departments` audit triggers:** `trg_employee_departments_audit_*` in `db/03_triggers.sql` (114 auditable insert triggers on fresh import). Live fix when table exists but triggers are missing: `db/migrations/employee_departments_audit_triggers.sql`.
- **`emails` send log (private-data exempt):** `from_email`, `to_email`, `cc_email` (`varchar(255)` NOT NULL), `status` enum `sent` | `failed` | `received`, `is_archived` `TINYINT(1) NOT NULL DEFAULT 0` (hide from Send Logs list; no `trg_emails_audit_*` in `03_triggers.sql`). Seeds in `02_data.sql` / `02_data_sample.sql` set `is_archived = 0`.

## 7. File Structure
- `01_schema.sql` — DDL (`DROP DATABASE`, `CREATE TABLE`, …)
- `02_data.sql` — seed DML (`INSERT`, `UPDATE`, `DELETE`, `SET @replicate_source_company_id`)
- `02_data_sample.sql` — **runtime-only** Add sample data / MBQA `sample_data` templates (company `1` markers in file; seeder stamps active tenant). **Not** in import order. Build: `php scripts/extract_02_data_sample.php --apply`.
- `03_triggers.sql` — audit triggers + `SET FOREIGN_KEY_CHECKS=1`
- `index.html` — directory listing prevention

## 8. Import (Laragon / local)

**Preferred (single session):**

```bash
bash scripts/import_database_split.sh
```

**MySQL port (`MYSQL_PORT`):** `import_database_split.sh` and `verify_database_sql_import.sh` honour `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_USER`, and `MYSQL_PASSWORD`. Default **`MYSQL_PORT` is 3307** (Dunebox). **GitHub Actions** job **database-import** sets **`MYSQL_PORT=3306`** to match the workflow MySQL service (`3306:3306`). Align CLI import with application `.env` **`DB_PORT`** when not on Dunebox (for example Laragon on **3306**: `MYSQL_PORT=3306 bash scripts/import_database_split.sh`).

**CI parity (local):** `bash scripts/verify_database_sql_import.sh` — same wrapper CI uses after setting port **3306** in `.github/workflows/smoke.yml`.

**Manual (one piped session only — use `bash scripts/import_database_split.sh` when possible):**

```cmd
cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
(type db\01_schema.sql & echo. & type db\02_data.sql & echo. & type db\03_triggers.sql) | "D:\dunebox-v1.0.6\system\apps\mysql\mysql-8.0.45-winx64\bin\mysql.exe" -h 127.0.0.1 -P 3307 -u root -psecret --default-character-set=utf8mb4
```

Do **not** run schema, data, and triggers as three separate `mysql` CLI imports.

**Verify after import:** `php scripts/verify_database_schema.php` — compares live `information_schema` to `CREATE TABLE` names in `db/01_schema.sql` (currently **148** tables).

## Finance tables (AP/AR, payments, budget actuals)
- Lookups: `tax_rates`, `paid_statuses`, `payment_modes` (tenant-scoped; replicated from company 1 in `02_data.sql`).
- Integration: `integration_accounts` (optional `gl_account_id` bridge), `bank_accounts`.
- AP/AR documents: `bills` + `bill_line_items`, `invoices` + `invoice_line_items` (`invoices.customer_id` → `customers`, optional).
- **Customers:** `customer_statuses` + `customers` (AR master; separate from `suppliers`).
- **Payments:** `finance_payment_allocations` links `bank_accounts` / `payment_modes` to exactly one of `bill_id` or `invoice_id`; `amount_due` on bill/invoice headers is recomputed in PHP (`includes/itm_finance_payments.php`).
- **Attachments:** `finance_attachments` metadata; files on disk under `finance/{company_id}/{parent_table}/{document_key}/` (`deny_all` via `FINANCE_UPLOAD_PATH`); served through each module’s `attachment.php`. Helpers: `includes/itm_finance_attachments.php`. Migration: `db/migrations/finance_attachments.sql`.
- **Expense recurrence:** `expense_recurrence` lookup; `expenses` columns `is_recursive`, `next_run_date`, `recurrence_end_date`, `recurrence_source_expense_id`; runner `php scripts/run_expense_recurrence.php`.
- **Budget actuals:** extended `expenses` (`posting_date`, `paid_status_id`, EUR `currency_code`, optional `bill_id` and `invoice_id`; `invoice_number` stores the source document number when posted); `modules/budget_report/` sums Posted/Paid only via `COALESCE(posting_date, date)`.
- Finance data is maintained via module CRUD, bill/invoice **Post to expenses**, and Excel import (expenses AP aliases in `includes/itm_expenses_ap.php`). No automated external sync webhooks.

## 10. Common Pitfalls
- Importing `03_triggers.sql` before `02_data.sql` fills `audit_logs` during seed load. [Cursor-Valid]
- Separate `mysql` CLI calls drop `@replicate_source_company_id` before replication `INSERT … SELECT`. [Cursor-Valid]
- Multi-company seed `employees` (companies 2–5) subquery `employment_status_id` / `access_level_id` **before** the late `@replicate_source_company_id` block — an early `access_levels` + `employee_statuses` replication block must run immediately before that `employees` INSERT in `02_data.sql`. [Cursor-Valid]
- **`it_locations`, `racks`, `suppliers`, `idfs` replication:** late-block `INSERT … SELECT` must remap lookup FKs (`type_id`, `location_id`, `status_id`, `rack_id`) to the target tenant by name — not copy company-1 numeric ids (matches `equipment` / `inventory_items`). Order: `it_locations` → `racks` → `suppliers` → `idfs` (idfs after tenant locations/racks exist). Live backfill: `db/migrations/seed_replicate_location_rack_supplier_fk_remap.sql`. Regression: `php scripts/detect_fk_dropdown_ui_risk.php` exit `0` after fresh import. [Cursor-Valid]
- Seed admin `role_id` (usernames `LIKE 'Admin%'`) must be set **after** `INSERT IGNORE INTO employee_roles …` replication in `02_data.sql`. An earlier UPDATE only binds company 1; `itm_is_admin()` stays false for Admin2–Admin5 until `role_id` points at the tenant `Admin` role. Live fix: `db/migrations/employees_seed_admin_role_id.sql`. [Cursor-Valid]
- `employee_sidebar_preferences` seed must bind **`employee_id`** to each tenant seed admin (`Admin`, `Admin2`–`Admin5`), not `employee_id = 1` on every `company_id`. Cross-tenant sessions use `Admin4` etc.; missing prefs force a sparse default sidebar. Live fix: `db/migrations/employee_sidebar_preferences_seed_admins.sql`. [Cursor-Valid]
- **Sidebar layout storage:** SideMenu show/hide and section/item order live in **`employee_sidebar_preferences`** (`entry_type`, `entry_id`, **`section_id`** must match canonical catalog parent via `itm_sidebar_default_item_parent_map()` — `itm_reconcile_employee_sidebar_preferences_canonical_sections()` fixes legacy wrong sections on load). **`ui_configuration`** holds button positions, app name, favicon, equipment-type visibility JSON, and `module_icon_overrides` only — not sidebar order/visibility (legacy `sidebar_*` columns dropped by `itm_ensure_ui_configuration_table()` on live DBs). [Cursor-Valid]
- **Demo users (`demo1`–`demo5`):** company 1 single-module QA accounts (password = username); roles `Demo Tickets` / `Demo Audit` / `Demo Visitors` / `Demo Request Password` / `Demo Equipment` with one `role_module_permissions` row each; sidebar prefs limited to Dashboard + Settings + primary module. Seeded in `02_data.sql`; live backfill: `db/migrations/demo_module_users.sql` or `php scripts/fast_create_acc.php --seed-demo-bundle --company=1`.

## 12. Module Owner Notes (Optional)
Path helpers: `includes/itm_database_sql_source.php`. Catalog: `scripts/SCRIPTS.md`.
