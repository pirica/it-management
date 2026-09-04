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
- Database import expects an empty or replaceable `itmanagement` database (destructive `CREATE TABLE` / seed import).
- Admin step updates an existing seed row (`username = Admin` by default) — import must run before step 6.

## 4. Business Rules (Critical for Agents)

- Entry defines `ITM_SETUP_WIZARD` before `config/config.php` (no employee session; soft DB connection failure).
- Completion writes `setup/.installed` and deletes `setup/index.php` + `setup/includes/itm_setup_wizard.php` (step 8).
- While `setup/.installed` exists, wizard redirects to `login.php` unless `?force=1` (re-run only when entry file restored manually).
- `config/config.php` strips `/setup` from `BASE_URL` detection (same pattern as `/scripts`).
- Production profile in step 5 forces `ITM_DEV=0`, `ITM_SKIP_FORCE_PASSWORD_CHANGE=0`, and disables browser error reporting on `ui_configuration`.
- Not wired to CI smoke — operators run manually before first login.

## 5. UI Behavior Requirements

Eight steps (sidebar + main panel): install folder → verify files → database → extensions → settings → admin → sample data → finish.

Step 1 exposes an editable **project root** field (pre-filled from PHP `ROOT_PATH`, formatted with Windows backslashes when applicable). **New path:** when the folder does not exist, the wizard creates it (`0755`), downloads `pirica/it-management` from GitHub (`git clone` with ZIP fallback), and verifies `db/01_schema.sql`. **Existing path:** fails unless it matches the current runtime install folder (in-place setup). Optional `.env`: `ITM_SETUP_GITHUB_REPO`, `ITM_SETUP_GITHUB_BRANCH`. Session path drives step 2 database-bundle checks; `.env` and upload hardening still target the runtime `ROOT_PATH` for the active request.

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

Sample data step defaults to `company_id = 1`. Admin update targets one `employees.username` (global username uniqueness).

## 9. UI Configuration Dependencies

Step 5 may set `enable_all_error_reporting` on all `ui_configuration` rows when the checkbox is cleared for production.

## 10. Known Pitfalls

- Large `db/` import via `mysqli_multi_query` may fail when `max_allowed_packet` is low — wizard falls back to `mysql` CLI when mysqli import fails.
- Step 8 self-deletes `index.php`; keep `setup/.installed` for lock detection.
- Do not leave the wizard reachable in production — run [check_prod_hardening.php?run=1](http://localhost/it-management/scripts/check_prod_hardening.php?run=1) before go-live.

## 11. Testing / Verification

Manual: [setup/index.php](http://localhost/it-management/setup/index.php) (no login until finish).

After finish: `login.php` with rotated admin credentials.

## 12. Related Documentation

- `handoff.md` — first-time install
- `docs/ENV.md` — `.env` keys written in steps 3 and 5
- `scripts/import_database_split.php` — equivalent CLI/browser import
