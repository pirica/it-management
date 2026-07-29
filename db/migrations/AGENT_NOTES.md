# AGENT_NOTES.md - db/migrations/

## 1. Module Purpose
Incremental DDL scripts for **existing** databases. Fresh installs use the matching `CREATE TABLE` blocks in `db/01_schema.sql` (and seeds in `db/02_data.sql` when needed).

## 4. Business Rules (Critical for Agents)
- **Naming:** `db/migrations/{module}_{subject}.sql` (lowercase module slug, underscore subject). Examples: `explorer_share.sql`, `employee_totp.sql`, `floor_plans_share.sql`.
- **No `ALTER TABLE` in migrations (hard rule):** copy the current table definition from `db/01_schema.sql`, paste it into the migration file, apply the change, and ship the **full `CREATE TABLE`** block. Do not use `ALTER TABLE` / `MODIFY` / `ADD COLUMN` in `db/migrations/`.
- **No staging tables (hard rule):** do not use `{table}_new`, `RENAME TABLE`, or `INSERT … SELECT` swap patterns. Migrations use **copy/paste replacement** only:
  1. `SET FOREIGN_KEY_CHECKS = 0`
  2. `DROP TABLE IF EXISTS \`{table}\`;`
  3. `CREATE TABLE \`{table}\` ( … full target definition copied from `db/01_schema.sql` with your change … );`
  4. `SET FOREIGN_KEY_CHECKS = 1`
- **Data warning:** `DROP TABLE` removes existing rows. Back up or export data before applying on production; re-seed or restore manually when needed.
- **Pair every migration with canonical schema:** mirror the same table shape in `db/01_schema.sql` (and `db/02_data.sql` when seeds change) in the **same PR**.
- **Apply order:** run migrations manually on live DBs in filename order; there is no migration runner yet.
- **Live verification:** `php scripts/verify_db_migrations.php` (browser + CLI, Admin) probes each file in this folder against `information_schema` and seed data; `--json` / `?format=json` for machine output.
- **No audit triggers** on private-data tables listed in `AGENTS.md` → Private data — no audit trail.

## 7. File Structure
- `{module}_{subject}.sql` — one focused table replacement per file (`DROP TABLE` + full `CREATE TABLE`, not `ALTER`)
- `employee_totp.sql` — `employees` table with `totp_secret` + `totp_enabled` (mirrors `db/01_schema.sql`; destructive — re-seed employees after apply)
- `employee_roles_sidebar_show.sql` — `employee_roles` table with `sidebar_show` (`TINYINT(1) NOT NULL DEFAULT 1`; destructive — re-seed roles after apply)
- `employees_seed_admin_role_id.sql` — DML only: sets `role_id` on seed admins (`username LIKE 'Admin%'`) to each tenant's `Admin` `employee_roles` row (idempotent)
- `employees_employee_departments.sql` — adds `employee_departments` junction table + backfill from `employees.department_id` (mirrors `db/01_schema.sql`)
- `employee_sidebar_preferences_seed_admins.sql` — DML only: reassigns sidebar layout rows to each company's seed admin employee (`username LIKE 'Admin%'`)
- `demo_module_users.sql` — DML only: idempotent seed for `demo1`–`demo5` roles, RBAC rows, employees, `employee_companies`, and `ui_configuration` (company 1; `enable_chatbot = 1`); prefer `php scripts/fast_create_acc.php --seed-demo-bundle` for sidebar prefs refresh
- `seed_replicate_location_rack_supplier_fk_remap.sql` — DML only: remaps cross-tenant `type_id` / `location_id` / `status_id` / `rack_id` on companies 2–5 for `it_locations`, `racks`, `suppliers`, `idfs` (pairs with fixed `@replicate_source_company_id` block in `db/02_data.sql`)
- `ui_configuration_enable_chatbot_active.sql` — DML only: `UPDATE ui_configuration SET enable_chatbot = 1 WHERE enable_chatbot = 0` (idempotent backfill to schema default)
- `companies_audit_triggers.sql` — trigger-only: fixes `trg_companies_audit_*` `audit_logs.company_id` fallback (`NEW.id` / `OLD.id`)
- `employee_departments_audit_triggers.sql` — trigger-only: adds `trg_employee_departments_audit_*` (mirrors `db/03_triggers.sql`; run after `employees_employee_departments.sql` on live DBs)
- `it_settings_chat_same_tenant.sql` — `it_settings` with `chat_same_tenant` (`TINYINT(1) NOT NULL DEFAULT 1`; destructive — includes seed INSERT for companies 1–5)
- `expenses_quotation_order.sql` — `expenses` with `quotation_order` + `quotation_order_accepted` after PO fields (mirrors `db/01_schema.sql`; destructive — re-seed or restore expenses after apply)
- `live_chat_typing_audit_columns.sql` — `live_chat_typing` with standard soft-delete/meta columns (mirrors `db/01_schema.sql`; destructive — typing rows are ephemeral)
- `appointment.sql` — all four `appointment_*` tables (mirrors `db/01_schema.sql`; destructive)
- `appointment_booking_lock.sql` — `appointments` with `booking_lock` unique slot index (destructive — drops appointment rows; requires `appointment_type` table when using current schema)
- `appointment_type.sql` — `appointment_type` lookup + `appointments.appointment_type_id` (destructive — drops `appointments`; seeds `in_person` / `remote` per company)
- `appointments_assigned_confirmed.sql` — `appointments` with `assigned_to_employee_id` (FK `employees`, SET NULL) and `is_confirmed` (destructive — drops appointment rows)
- `appointment_settings_default_modality.sql` — `appointment_settings.default_appointment_modality` enum (`remote` default; preserves rows via backup table)
- `hotel_booking_portal_rate_plans.sql` — `hotel_booking_portal_rate_plans` per-hotel Step 2 cancellation policy URLs (mirrors `db/01_schema.sql`; new table — run seeds or admin ensure for rows)
- `booking_rooms_types_upgrade.sql` — `booking_rooms_types` with `upgrade_to_room_type_id`, `upgrade_price_per_night`, `upgrade_pitch` (destructive — re-seed room types from `db/02_data.sql` after apply)

## 12. Module Owner Notes (Optional)
Catalog pointer: `AGENTS.md` → Database & Schema Rules → **Incremental migrations (`db/migrations/`)**.
