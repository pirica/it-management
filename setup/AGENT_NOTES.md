# AGENT_NOTES.md - Setup Wizard

## 1. Module Purpose

Browser-based first-run installer (`setup/index.php`) — cPanel-style step wizard for folder confirmation, file checks, database import, PHP extensions, `.env` profile, admin password rotation, optional sample data, and removal of the setup entry point.

## 2. Key Tables

Touches the full schema when **Import database bundle** runs (`db/01_schema.sql` → `02_data.sql` → `03_triggers.sql`). Post-import steps may update:

- **employees** — primary admin password and profile fields (step 6)
- **ui_configuration** — `enable_all_error_reporting` when environment settings are saved (step 5)
- Any table targeted by **Install sample data** via `itm_seed_all_tables_from_database_sql()` (step 7)

## 3. Required Relationships

- Requires writable project root for `.env` and upload trees (`itm_ensure_upload_directory()`).
- Database import expects an empty schema or explicit replace confirmation when tables already exist (destructive `DROP DATABASE` + bundle import).
- Admin step updates an existing seed row (`username = Admin` by default) — import must run before step 6.

## 4. Business Rules (Critical for Agents)

- Entry defines `ITM_SETUP_WIZARD` before `config/config.php` (no employee session; soft DB connection failure).
- Completion writes `setup/.installed` and deletes `setup/index.php` + `setup/includes/itm_setup_wizard.php` (step 8).
- While `setup/.installed` exists, wizard redirects to `login.php` unless `?force=1` (re-run only when entry file restored manually).
- `config/config.php` strips `/setup` from `BASE_URL` detection (same pattern as `/scripts`).
- Production profile in step 5 forces `ITM_DEV=0`, `ITM_SKIP_FORCE_PASSWORD_CHANGE=0`, and disables browser error reporting on `ui_configuration`.
- **`.env` is written on step 7** (sample data install or skip) and re-checked on step 8 finish — not on step 3 or 5. Steps 3–6 keep database and environment settings in wizard session; `itm_setup_wizard_connect_database()` prefers session creds over on-disk `.env` so a stale `.env` cannot break later steps or force a jump back to step 3.
- Not wired to CI smoke — operators run manually before first login.

## 5. UI Behavior Requirements

Eight steps (sidebar + main panel): install folder → verify files → database → extensions → settings → admin → sample data → finish.

Step 1 exposes an editable **project root** field with a **💾** save control (`title="Save"`). Clicking save POSTs `step1_preview` (JSON) to validate the folder without advancing, repair Windows slashes, persist the path to wizard session state when valid, and refresh **Auto-detect**, **Document root** (repaired `DOCUMENT_ROOT` display), **Detected BASE_URL**, **Docroot aligned**, and **ITM_APP_URL**. Collapsed Windows paths (backslashes stripped) repair using the runtime install folder as a template — including sibling folder names such as `it-management5` when the wizard runs from `it-management3`. **Download** provisions/creates the folder (GitHub archive when missing), shows **Please wait...** while the POST runs, and moves to step 2. When the target folder already exists (and is not the current in-place install), Download requires browser `confirm()` plus POST `confirm_replace_folder=1` before wiping all files inside and re-downloading. An informational **localhost ports** panel probes `127.0.0.1:80`, `127.0.0.1:443`, `localhost:80`, and `localhost:443` on page load (`🟢 Open` / `🔴 Closed` / `⭕ Unknown`) — display only; never blocks install steps. Step 1 also includes a **MySQL port** number field (saved to wizard session on Download) that **auto-selects** the open loopback listener from the informational probe (`127.0.0.1:3306` / `:3307`; prefers **3306** when both are open). When neither port is open, the field falls back to `.env` `DB_PORT` then **3306**. Step 3 pre-fills **MySQL port** from step 1 / open loopback probe / `.env` `DB_PORT` (saved session and step 1 values win over probe and `.env`).

Step 3 probes MySQL at the server level before selecting a schema. When the named database does not exist, **Test connection** returns an info flash (not raw `Unknown database`) and shows **Create database** (`step3_create_db`). When the schema exists with tables, import requires browser `confirm()` plus POST `confirm_replace=1`; the wizard **DROP DATABASE** + recreates the schema before `01_schema → 02_data → 03_triggers` import. Custom `DB_NAME` values rewrite canonical `` `itmanagement` `` DDL in the import bundle so tables land in the selected schema. Import uses DELIMITER-aware mysqli execution (required for `03_triggers.sql`); mysql CLI via `proc_open` array command + `MYSQL_EXE`/`itm_resolve_cli_mysql_binary()` is the fallback when mysqli import fails. Success requires expected table and trigger counts from `information_schema`.

Step 7 lists active seed companies from `companies` (or the canonical five-company catalog from `db/02_data.sql` when the step 3 session DB is not connected yet) with per-company checkboxes, always-visible **Select All** / **Unselect All**, and POST `sample_company_ids[]` to `itm_setup_wizard_install_sample_data_for_companies()` (wraps `itm_seed_all_tables_from_database_sql()` per tenant).

Step 2 always re-runs file verification on page load (no stale cached `file_checks` from a prior runtime path). Writable upload paths and the **Confirmed project root** header resolve via `itm_setup_wizard_project_root()`, which repairs collapsed Windows session paths (e.g. `C:Users…it-management2`) and, after step 1 is complete, never falls back to the PHP runtime install folder when the session path differs.

**Path HTML output:** use `itm_setup_wizard_h()` for plain text and `itm_setup_wizard_h_path_display()` for filesystem paths (non-breaking hyphens + `<wbr>` after `\` / `/`). Step 2 verify rows use `label` + `path` on separate lines inside the cell — **no** horizontal scrollbars. Do **not** use `sanitize()` for paths (it strips `\`).

All mutating POSTs use `itm_try_post_csrf()`. Emoji-only navigation buttons follow NO MIXED on back/submit controls.

## 6. API Actions (If Applicable)

None — form POST actions only (`wizard_action`).

## 7. File Structure

| File | Role |
|------|------|
| `setup/index.php` | Wizard UI + POST handlers (removed on finish) |
| `setup/includes/itm_setup_wizard.php` | Probes, `.env` writer, import, admin/sample helpers |
| `setup/.installed` | Lock file written on finish |
| `setup/index.html` | Directory listing placeholder |

## 8. Multi-Tenancy Rules

Sample data step defaults to company **1** when no prior selection is stored; step 7 lists every active seed company with checkboxes, **Select All** / **Unselect All**, and installs `db/02_data_sample.sql` rows for each checked company via `itm_setup_wizard_install_sample_data_for_companies()`. Admin update targets one `employees.username` (global username uniqueness).

## 9. UI Configuration Dependencies

Step 5 may set `enable_all_error_reporting` on all `ui_configuration` rows when the checkbox is cleared for production.

## 10. Known Pitfalls

- Large `db/` import may fail when `max_allowed_packet` is low — wizard falls back to `mysql` CLI when mysqli import fails. `03_triggers.sql` uses `DELIMITER $$` blocks; mysqli import must use `itm_database_migrations_execute_sql_text()` (not `mysqli_multi_query`). Some trigger clusters use semicolon-terminated `DROP TRIGGER` lines **inside** an active `$$` delimiter block — the parser must flush those as separate statements (same as the mysql CLI), not batch them with the following `CREATE TRIGGER`.
- Step 8 self-deletes `index.php`; keep `setup/.installed` for lock detection.
- Do not leave the wizard reachable in production — step 8 links to [check_prod_hardening.php?run=1&enforce=1](http://localhost/it-management/scripts/check_prod_hardening.php?run=1&enforce=1) (Administrator; new tab) and [login.php](http://localhost/it-management/login.php) (new tab) before finish.

## 11. Testing / Verification

Manual: [setup/index.php](http://localhost/it-management/setup/index.php) (no login until finish).

Regression: `php scripts/verify_setup_wizard_project_root.php` — collapsed Windows path repair, step 1 session root vs runtime fallback, step 2 upload subdirectories, `itm_setup_wizard_h()` vs `sanitize()` path escaping, localhost port status labels.

Regression: `php scripts/verify_setup_wizard_database.php` — step 3 `itm_setup_wizard_probe_database()`, SQL bundle rewrite for custom `DB_NAME`, create/reset helpers, `needs_create` / `needs_replace_confirm` flags, mysqli import + trigger count on empty schema (requires MySQL; skips live create tests when server unreachable).

After finish: `login.php` with rotated admin credentials.

## 12. Related Documentation

- `handoff.md` — first-time install
- `docs/ENV.md` — `.env` keys written on step 7 (and verified on step 8 finish)
- `scripts/import_database_split.php` — equivalent CLI/browser import
