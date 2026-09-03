# AGENT_NOTES.md - Root Project

> **Authoritative process and module standards:** `AGENTS.md`. This file captures system-wide context, entry-point behaviour, and operational pitfalls that agents should read at session start alongside `AGENTS.md`.

## 1. Module Purpose

The IT Management System is a multi-tenant legacy procedural PHP application (PHP 7.4) for IT infrastructure, employees, budgets, appointments, and helpdesk operations with zero external dependencies (no Composer, no NPM).

---

## 2. Key Tables (system-wide)

- **companies** — Tenant organizations; scopes most application data.
- **employees** — Authentication and profiles (`username`, `password`, `totp_secret`, `totp_enabled`, `reports_to`).
- **employee_roles** / **role_module_permissions** — RBAC (see `modules/roles_permissions/`).
- **employee_companies** — Extra tenant grants for the company switcher.
- **employee_sidebar_preferences** — Per-user sidebar visibility (not `ui_configuration`).
- **ui_configuration** — Per-user UI layout, toggles, and API tier (`tier`, `api_key`, `rate_limit_*` counters).
- **audit_logs** — DML audit trail from triggers (and PHP hooks where applicable).
- **appointments** (+ lookup tables `appointment_*`) — Self-service IT visit booking (`modules/appointments/`).
- **modules_registry** / **company_module_access** — Module catalog and per-tenant enablement.

---

## 3. Required Relationships

- **Multi-tenancy:** Tenant tables carry `company_id` → `companies(id)`. Queries use the session active company unless a flow explicitly uses the employee home company (profile save on `user-config.php`).
- **Private modules:** Passwords, notes, private contacts, and similar tables also filter by `employee_id` — see `AGENTS.md` private-data sections.
- **Audit actors:** `config/config.php` sets MySQL `@app_employee_id` / `@app_company_id` for trigger payloads.
- **Parent deletes:** When child FKs lack `ON DELETE CASCADE` / `SET NULL`, detach or clear children for the active `company_id` before parent delete (see `AGENTS.md` bulk delete notes).

---

## 4. Business Rules (Critical for Agents)

- **Security:** CSRF on POST (`itm_require_post_csrf()` / module helpers). Prepared statements for all user data (`mysqli`).
- **Multi-tenancy:** Scope reads/writes by session `company_id` except documented exceptions (companies table, employee-home profile UPDATE, employee-scoped vault modules).
- **Architecture:** Procedural PHP; `config/config.php` bootstraps the app. No Composer/NPM.
- **API rate limits:** Free tier — unlimited, no API key, **session required**. Paid tiers — hourly caps, API key required. See `AGENTS.md` → **API keys and rate limits** and `includes/itm_api_rate_limit.php`.
- **`db/` hygiene:** No executable `ALTER TABLE` in `01_schema.sql`; migrations use `DROP` + `CREATE` under `db/migrations/`. Multi-company seed admins use tenant-correct role/status lookups — see `AGENTS.md` → **Database & Schema Rules**.
- **Login session rotation:** `login.php` calls `session_regenerate_id(true)` after successful password verification. Admin success path calls `itm_switch_active_company_session()` for the first active company when needed.
- **Login CSRF failures:** `login.php` uses `itm_try_post_csrf()` and renders `Invalid CSRF token.` inside `.container` (does not call `itm_require_post_csrf()` which exits before HTML). Session cookie path is scoped via `ITM_SESSION_COOKIE_PATH` for subdirectory installs. CSRF tokens are also mirrored to the readable `itm_csrf` cookie (double-submit) so the first POST still validates when `PHPSESSID` is missing but the form token matches the cookie (common in embedded browser previews). `login.php` mints the form token only after POST handling, not before CSRF validation.
- **Public auth CSRF (in-page errors):** `forgot-password.php`, `register.php`, `reset-password.php`, `index.php` (company picker), `logout.php`, `booking/auth/login.php`, and `booking/auth/register.php` use `itm_try_post_csrf()` and show errors inside their card/container markup. `user-config.php` and `includes/itm_vault_unlock.php` surface CSRF failures via flash/lock-screen error text instead of `die()`. `admin.php` company switch shows `crud_error` inside the Information card. `dashboard.php` company switch shows `crud_error` inside the Company card.
- **Stale session / plain-text guards:** `dashboard.php` and `user-config.php` redirect to `login.php` when the session `employee_id` row is missing (no `die('User not found.')` before HTML). `logout.php` shows **Method not allowed.** inside `.container` for non-GET/POST requests.
- **Entry pages / errors:** Root `index.php` must not force `display_errors`; use Settings-driven `enable_all_error_reporting` via `config/config.php` (default **off** — `0` in schema, seeds, and `itm_ui_config_defaults()`).
- **UTF-8:** `utf8mb4` end-to-end; do not strip emoji or punctuation to fix viewer mojibake — see `AGENTS.md` → **Character encoding**.

### Appointment booking (client validation)

In `js/appointment.js`, the schedule control stays clickable for validation: missing visit reason → `alert('--Select a reason for your appointment--')`; missing slot → `alert('Select an appointment time.')`. The button is disabled only during the schedule AJAX call. Slot concurrency uses `appointments.booking_lock` (unique per company). Regression: `php scripts/verify_appointment.php`.

---

## 5. UI Behavior Requirements

Flattened CRUD modules follow `AGENTS.md` (search/sort/pagination, bulk delete when `$totalRows >= $perPage`, `$displayFieldColumns = $uiColumns`, NO MIXED emoji action labels, `itm-actions-cell` + `data-itm-actions-origin="1"`, import endpoint attributes). Bespoke modules (calendar, explorer, appointment, `user-config.php`, etc.) are listed in `docs/list_bespoke_UI.txt` — match their real PHP, not the generic scaffold checklist.

---

## 6. API Actions (cross-cutting)

- **`scripts/api.php?rate_limit=1`** — Rate-limit probe (does not consume quota).
- **`modules/explorer/api.php`** — Tenant file manager; `downloadZip` only for own `Private/{username}_{employee_id}` tree.
- **`modules/knowledge_base/chat_api.php`** — Chatbot; CSRF + rate limit + HTML escape in JS.
- **`modules/appointments/api.php`** — `week_slots` (GET), `schedule` (POST + CSRF).

Module-specific JSON/import endpoints are documented in `scripts/api.php` and per-module `AGENT_NOTES.md`.

---

## 7. File Structure (high level)

- **config/**, **includes/**, **modules/**, **scripts/**, **db/** — application and schema.
- **login.php** — Authentication; regenerates session id on success; Admin login calls `itm_switch_active_company_session()` for the initial company so welcome email/username match the tenant (not only after manual company switch).
- **index.php** — Company selection after login (no forced error display); skipped when `itm_try_auto_select_single_company_session()` finds exactly one accessible tenant (also from `login.php` for non-admins).
- **dashboard.php** — Employee landing: **Admin-only company switcher** (`.itm-emp-dash-company-switch`; hidden for other roles; POST `company_id` ignored unless `itm_is_admin()`; `itm_try_post_csrf()` + `itm_switch_active_company_session()` from `itm_company_session_login_employee_id()`; options from `itm_list_employee_accessible_companies()`). Cross-tenant **Admin** switches also change the visible user (hero / session `employee_id` / `username`) to that tenant's seed Admin (`Admin2` … `Admin5`); `login_employee_id` stays the authenticated login. CSRF failures show `crud_error` in the Company card (does not `die()`); **smart widgets** (`includes/itm_dashboard_widgets.php` — role/RBAC-aware metrics + Chart.js sparklines; per-employee pin/unpin on [user-config.php](http://localhost/it-management/user-config.php)) above personal stat cards via `includes/itm_employee_dashboard.php` / `itm_employee_dashboard_cards.php`; **My Activity** → `modules/myactivity/`; **Private** section cards; no Sidebar Prefs or Audit Logs cards. Regression: `php scripts/verify_employee_dashboard.php`, `php scripts/verify_dashboard_widgets.php`.
- **admin.php** — Admin company overview: module totals exclude soft-deleted rows; **Active** / **On Leave** via `itm_employee_count_by_employment_status_name()`; **Online now** via session presence; **Settings** + **Scripts** cards; sections via `includes/itm_admin_dashboard_cards.php`. Regression: `php scripts/verify_dashboard_active_employees.php`, `php scripts/verify_admin_page_gate.php`.
- **css/styles.css** — Global stylesheet (see **`css/AGENT_NOTES.md`**).
- **phpunit/** — PHPUnit PHAR and tests; runner **`scripts/run_tests.php`**; coverage **`phpunit/coverage/html/coverage.html`**. See **`phpunit/AGENT_NOTES.md`** and **`scripts/SCRIPTS.md`**.
- **handoff.md** — Multi-tenant project handoff, WAMP/LAMP setups (Dunebox & Laragon Portable), active development, security vulnerability remediation history, and incoming developer strategic guidance.

---

## 8. Multi-Tenant Rules

- Default list/CRUD queries bind `company_id` from `$_SESSION['company_id']`.
- Vault/private modules also bind `employee_id` from the session.
- Profile and file paths under `files/{home_company_id}/Private/{username}_{employee_id}/` use the employee **home** company, not only the switched tenant.
- Admins use `employee_companies` + session switcher; switching updates active `company_id` and remaps session `employee_id` to that tenant's seed Admin (`Admin2` … `Admin5`) while `login_employee_id` stays the authenticated user. Non-admins keep the same `employee_id` across grants.

---

## 9. Audit Logging Requirements

- **Database triggers:** `trg_{table}_audit_insert|update|delete` in `db/03_triggers.sql` write to `audit_logs` on DML (not gated by the Settings `enable_audit_logs` toggle).
- **Private-data exempt tables** (no triggers / no PHP audit copy): full list in `AGENTS.md` → **Private data — no audit trail** (`emails`, vault tables, `share_sessions`, todo/notes/events/bookmarks, live chat bodies, etc.).

---

## 10. Common Pitfalls

- Bypassing session-based company isolation. [Cursor-Valid]
- Defining `itm_is_admin()` after `itm_ensure_company_context_employee_session()` in `config.php` — Admin company switch then keeps the previous tenant's user on the next GET. [Cursor-Fixed]
- Introducing external libraries. [Cursor-Valid]
- Forgetting to update `db/` when changing the schema. [Cursor-Valid]
- Editing `db/*.sql` by hand instead of keeping `01_schema.sql` / migrations aligned. [Cursor-Valid]
- Allowing arbitrary line-wrapping in administrative or diagnostic reporting tables (use `white-space: nowrap` and a horizontal scroll wrapper). [Cursor-Fixed]
- Session fixation: reusing the pre-login session id after authentication without regeneration. [Cursor-Fixed]
- Session cookie missing HttpOnly / SameSite / Secure (when HTTPS). [Cursor-Fixed]
- Hardcoding `display_errors` on `index.php` instead of Settings-driven config. [Cursor-Fixed]
- `user-config.php` System Access SELECT must not `array_merge` hardcoded meta names absent from `employee_system_access` (e.g. inventing `changed_at` → prepare failure). [Cursor-Fixed]
- `user-config.php` profile form must save and re-display `birthday` / `hide_year`; blank birthday and unchecked hide_year must clear/persist correctly. [Cursor-Fixed]
- `user-config.php` profile UPDATE must use the employee home `company_id`, not the tenant-switcher session company. Theme must set `<html data-theme>` + CSS variables. [Cursor-Fixed]
- **Manual SQL string false positives:** URL href builders are not SQL — `scripts/check_manual_sql_string.php`; see `scripts/SCRIPTS.md`.
- `user-config.php` profile photo: broken image often means non-absolute `modules/explorer/file.php` URL — use `emp_profile_photo_url()` + `BASE_URL`. [Cursor-Fixed]
- Employee dashboard stats: `includes/itm_user_config_stats.php`; `floor_plans` uses `created_by`, not `created_by_employee_id`. [Cursor-Fixed]
- `db/` tenant replicas must resolve FK seeds by `company_id` + business key, not assumed shared auto-increment ids. [Cursor-Fixed]
- **Auto-scaffold pollution:** `SHOW TABLES` / MBQA may create stub `modules/{table}/` dirs — use `itm_module_dir_is_standard_crud_scaffold()` to detect; `list_active_and_checkboxes` skips stubs and bespoke slugs from `docs/list_bespoke_UI.txt`. [Cursor-Valid]
- **Directory listing / uploads:** Every repo folder needs `index.html`; upload trees need `itm_ensure_upload_directory()` policies (`upload`, `deny_http`, `deny_all`) — see `AGENTS.md` and `scripts/AGENT_NOTES.md`.
- **Root `.env` HTTP deny:** Repository root `.htaccess` must include `<Files ".env">` with `Require all denied` (and Apache 2.2 `Deny from all` fallback) so misconfigured docroots cannot serve credentials; regression `ITM-PENTEST-023` in `php scripts/verify_pentest_report.php`.

---

## 11. Examples of Safe Code Patterns

### Safe SELECT

```php
$stmt = $conn->prepare('SELECT id, name FROM departments WHERE company_id = ? AND deleted_at IS NULL AND id = ?');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
$result = $stmt->get_result();
```

### Safe INSERT (tenant-stamped)

```php
$stmt = $conn->prepare('INSERT INTO departments (company_id, name, active, created_by) VALUES (?, ?, 1, ?)');
$stmt->bind_param('isi', $companyId, $name, $employeeId);
$stmt->execute();
```

---

## 12. Module Owner Notes

- Regression and audit scripts: catalog in `scripts/scripts.php`; conventions in `scripts/SCRIPTS.md`; Cursor rule `../.cursor/rules/scripts-directory-standards.mdc` (Laragon `www/.cursor` only; all new scripts under `scripts/`, no uncatalogued probes).
- Useful gates: `php scripts/check_sql_injection_coverage.php`, `php scripts/check_ui_action_emoji.php`, `php scripts/check_fk_label_search_coverage.php`, `php scripts/list_active_and_checkboxes.php`, `php scripts/run_tests.php`.
- **Local dev login bypass:** `php scripts/bypass_login.php` → set browser `PHPSESSID` → open [dashboard](http://localhost/it-management/) (Admin, company 1, vault unlocked when script sets it).
- **Agent replies (localhost links):** whenever an agent cites `modules/…` or `scripts/…` paths in chat — summaries, audits, PR plans, not only defects — use markdown links with base `http://localhost/it-management/` and say **open in a new browser tab** (`AGENTS.md` step **7a**; `../.cursor/rules/local-dev-browser-links.mdc`). Cursor **SKILL.md** for table-count docs: `../.cursor/skills/sync-db-table-count-docs/SKILL.md`.

---

## 13. Employee Profile (`user-config.php`)

- **Vault Security (`#vault-security`):** Master-key create/change, optional TOTP 2FA, client-side key generator, secure one-time display after save. Notification-only emails on create/update (no plaintext secrets). See `docs/VAULT.md`.
- **Scoping:** Profile and security data scoped to `employee_id`.
- **Stat cards:** On `dashboard.php`; `user-config.php` is profile/preferences only; back link → `dashboard.php`.
- **Profile save (`action=update_profile`):** `work_email`, `mobile_phone`, `theme`, emergency contact fields, `birthday` (`itm_parse_date_input`), `hide_year` (checkbox). Full name readonly (Employees module). UPDATE uses employee **home** `company_id`. Regression: `php scripts/verify_user_config_profile.php`.
- **Profile photo:** `files/{home_company_id}/Private/{username}_{employee_id}/profile/`; display via `emp_profile_photo_url()`.
- **Layout:** `.layout-2col` — 280px sidebar column; stacks at `max-width: 768px`.
- **Personalized Sidebar:** `itm_sidebar_item_layout_visible()` for checkbox state (saved prefs without `employee_roles.sidebar_show` override); live sidebar still uses `itm_sidebar_item_effective_visible()`; saves to `employee_sidebar_preferences`; reload `$ui_config` from `itm_get_ui_configuration()` after save; Admin seed role uses `sidebar_show = 0` so hide/unhide applies after save.
- **Recent Activity:** `{action} in {table_name}` with link to module or catalog href.
- **Security flash messages:** Password, vault, and TOTP feedback at page top and above each section Save button.
- **Audit:** Profile and security changes logged to `audit_logs` when applicable.
- **System Access Overview:** `itm_crud_table_columns('employee_system_access')` (one cached DESCRIBE per request) then SELECT existing columns only; meta/audit columns excluded from flag counts.
