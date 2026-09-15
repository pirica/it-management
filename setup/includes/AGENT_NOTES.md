# AGENT_NOTES.md - Setup Wizard Helpers

## 1. Module Purpose

Procedural helper library for the first-run installer. Loaded from [setup/index.php](http://localhost/it-management/setup/index.php) and CLI regression scripts. Not a CRUD module.

## 2. Key Tables

Does not own tables. Import and sample-data helpers write the full tenant schema (`db/01_schema.sql`, `db/02_data.sql`, `db/03_triggers.sql`, `db/02_data_sample.sql`).

## 3. Required Relationships

- Parent: `setup/` wizard UI and [setup/AGENT_NOTES.md](../AGENT_NOTES.md).
- Sample seed: `includes/itm_sample_data_seed.php` via `itm_setup_wizard_install_sample_data()` / `itm_setup_wizard_install_sample_data_for_companies()`.
- MySQLi connect: `itm_mysqli_connect()` in `includes/bootstrap_helpers.php`.

## 4. Business Rules (Critical for Agents)

- Paths resolve under `itm_setup_wizard_project_root()` (confirmed step 1 folder), not necessarily PHP `ROOT_PATH`.
- `itm_setup_wizard_remove_entrypoint()` deletes only `{project_root}/setup/index.php`. Clears stat cache; `chmod` 0666 when not writable; `unlink` with one retry; failures include `error_get_last()`. Do not chmod 0777 and do not `@`-suppress unlink errors.
- `itm_setup_wizard_install_sample_data_for_companies()` seeds only the given company ids (isolation required).
- Keep helpers procedural; do not convert this folder to OOP/MVC.

## 5. UI Behavior Requirements

None in this folder — HTML lives in `setup/index.php`.

## 6. API Actions (If Applicable)

None. Callers are wizard POST `wizard_action` handlers and CLI verify scripts.

## 7. File Structure

| File | Role |
|------|------|
| `itm_setup_wizard.php` | Probes, `.env` writer, import, admin/sample helpers, Step 8 entrypoint removal |
| `AGENT_NOTES.md` | This file |

## 8. Multi-Tenancy Rules

Sample install stamps each selected `company_id`. Skip-sample cleanup leaves the step 6 administrator only.

## 9. UI Configuration Dependencies

Step 5 may update `ui_configuration.enable_all_error_reporting` through helpers invoked from `setup/index.php`.

## 10. Known Pitfalls

- Cross-folder install: unlink and `.env` target the confirmed root. A successful finish can delete another tree’s `setup/index.php` while the running wizard copy remains.
- CLI verify scripts: [verify_setup_wizard_database.php?run=1](http://localhost/it-management/scripts/verify_setup_wizard_database.php?run=1) defines `ITM_CLI_SCRIPT` + `ITM_SETUP_WIZARD` before `config/config.php` so sample seed can use `itm_parse_database_sql_inserts()` without a browser session.

## 11. Testing / Verification

- [verify_setup_wizard_project_root.php?run=1](http://localhost/it-management/scripts/verify_setup_wizard_project_root.php?run=1) (CLI; no login)
- [verify_setup_wizard_database.php?run=1](http://localhost/it-management/scripts/verify_setup_wizard_database.php?run=1) (CLI; no login; MySQL)

## 12. Related Documentation

- Parent [setup/AGENT_NOTES.md](../AGENT_NOTES.md)
- `scripts/SCRIPTS.md` — setup wizard verify rows
