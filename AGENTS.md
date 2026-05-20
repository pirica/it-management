# AGENTS.md

> [!IMPORTANT]
> **Role:** You are a Senior PHP Developer maintaining a legacy-style Procedural IT Management System.
> **Constraint:** Follow these rules strictly. Do not refactor to OOP, MVC, or modern frameworks. Keep logic flat and modular.

This document provides essential instructions, architectural constraints, and coding standards for AI agents working on the **IT Management System**.

## 🚀 Project Overview
A multi-company IT Asset Management System built with PHP and MySQL.
* **Design Philosophy:** GitHub Copilot theme (Light/Dark mode).
* **Architecture:** Procedural PHP with modular CRUD structures.
* **Multi-tenancy:** Data is strictly scoped by `company_id`.

## 🛠 Tech Stack & Environment
* **Backend:** PHP 7.4.33 (Strictly **MySQLi**, do NOT use PDO).
* **Database:** MySQL 8.0+.
* **Frontend:** Vanilla JS, Custom CSS (`css/styles.css`), No Frameworks.
* **Environment:** Apache 2.4+. **No Composer** dependency management.

## 📂 Directory Map
* `config/`: Core settings and `config.php`.
* `includes/`: UI components (headers, sidebars) and utility functions.
* `modules/`: Feature-specific CRUD logic.
* `scripts/`: Maintenance, security audits, and CLI tools. Catalog: `scripts/index.html`.
* `js/` & `css/`: Assets (use `css/styles.css`).
* **Required Dirs:** `images/`, `tickets_photos/`, and `backups/` must exist with write permissions.
* `scripts/api.php`: API Documentation

### Scripts directory (`scripts/`) — mandatory for every tool

The live catalog is **`scripts/index.html`**. Before merge, **verify every runnable file under `scripts/`** (and any new script you add) against this checklist.

#### 1. Catalog entry in `scripts/index.html` (required for every new script)

Add a table row with:

| Column | Content |
|--------|---------|
| **Script** | Filename (link if browser-safe to open) |
| **Access** | **Browser**, **CLI**, or both; mark **CLI-only** for bash, file writers, or `PHP_SAPI !== 'cli'` guards |
| **What it does** | Plain-language purpose (one short paragraph) |
| **How to use** | Exact browser URL/path, query flags, env vars, and CLI command: `php scripts/<name>.php [options]` |

Do not add a script under `scripts/` without updating `scripts/index.html`.

#### 2. Browser scripts (`scripts/*.php` opened in the browser)

* **Back link (required):** Every HTML report must show **← Scripts index** at the top, linking to `scripts/index.html` (relative `index.html` from `scripts/`).
  * Use `scripts/lib/script_browser_nav.php`: `require_once …/script_browser_nav.php`; then `itm_script_browser_nav_echo()`.
  * Plain-text-in-`<pre>` audits: use `scripts/lib/script_cli_output.php` (`itm_script_output_begin()`), which includes the same nav bar.
* **Human-readable results:** Browser output must explain findings in plain language (not only internal codes). Example: write “Duplicate dropdown option” rather than only `duplicate_dropdown_risk`. Include a short “what to do next” when useful.

#### Link creation rules (browser scripts — mandatory)

All outbound links in HTML script output must use helpers from **`scripts/lib/script_browser_nav.php`**. Do **not** hand-build `<a href="…">` with `BASE_URL`, `itm_script_module_index_url()`, or phpMyAdmin URLs.

| What appears in the report | Create a link? | How (browser) | Example |
|---------------------------|----------------|---------------|---------|
| **← Scripts index** | Always | `itm_script_browser_nav_echo()` | `index.html` (relative) |
| **Module folder** (`floor_plans`, `catalogs`, …) | Always | `itm_script_format_module_link('floor_plans')` or `itm_script_format_module_path_link('modules/catalogs/')` | `../modules/floor_plans/index.php` |
| **Database table name** (`catalogs`, `floor_plan_folders`, …) | **Only if** `modules/<table>/index.php` exists | `itm_script_format_table_link($tableName)` | `catalogs` → module link; `floor_plan_folders` → plain text only |
| **phpMyAdmin** | **Only on `scripts/index.html`** | Hardcode in catalog: `http://localhost/phpmyadmin/` | Never in other `scripts/*.php` output |
| **Edit row / actions** | When useful | `itm_script_module_relative_href_from_path('modules/name/', 'edit.php?id=5')` + `itm_script_external_link_html()` | `../modules/catalogs/edit.php?id=5` |

**Hard rules:**

1. **Relative paths only** for module links from `scripts/` (`../modules/…`). Never use `BASE_URL` or absolute URLs like `https://localhost/it-management/modules/…` in script reports.
2. **`target="_blank"`** and `rel="noopener noreferrer"` on every external/new-tab link — use `itm_script_external_link_html($href, $label)`.
3. **Table name ≠ module name** → no link. Example: table `floor_plan_folders` is not a module folder; show `<code>floor_plan_folders</code>` or plain text. Table `equipment` matches `modules/equipment/` → link the name to that module.
4. **phpMyAdmin** is documented and linked **only** in **`scripts/index.html`** (Laragon local MySQL). Audit/report scripts must not link table names (or anything else) to phpMyAdmin.

* **Exceptions (document in catalog):** JSON-only endpoints (e.g. `test_sql_injection.php`) and CLI entry points that redirect to a UI (e.g. `detect_fk_dropdown_ui_risk.php` → `detect_fk_dropdown_ui_risk_ui.php`) do not need HTML nav on the CLI path.

#### 3. CLI scripts

* Run from repository root: `php scripts/<script>.php [options]`.
* **Windows Laragon** when `php` is not on PATH: `<laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\<script>.php`.
* **Destructive or repo-writing tools** (`normalize_database_sql_created_at.php`, `apply_*_fix.php`, `repair_table_from_schema.php`, etc.): **CLI-only** — block web SAPI with `PHP_SAPI !== 'cli'` and show a small HTML page with **← Scripts index** + CLI instructions if opened in a browser.
* List exact commands and outcomes in the PR description when checks ran.

#### 4. Shared libraries (do not duplicate ad hoc)

| File | Use |
|------|-----|
| `scripts/lib/script_browser_nav.php` | **← Scripts index**, relative module links, table→module links when folder exists (`target="_blank"`) |
| `scripts/lib/script_cli_output.php` | Wrap browser audit output in `<pre>` + shared nav |
| `scripts/lib/sql_injection_detector.php` | SQLi signature tests (included by matrix / sandbox tools) |
| `scripts/lib/equipment_type_modules.php` | Canonical `modules/is_*` allowlist (`is_switch`, `is_server`, …); safe removal of regression-test scaffold dirs only (`*_itm_eqdct_*`, `*_itm_edct_*`) |

#### Equipment-type façade modules (`modules/is_*`) and clear-table tests

Canonical equipment-type wrappers live under **`modules/is_*`** (for example `is_switch`, `is_server`, `is_workstation`). They delegate to `modules/equipment/` and must **not** be deleted by maintenance scripts.

| Script | Role |
|--------|------|
| `scripts/lib/equipment_type_modules.php` | Shared allowlist + `itm_remove_equipment_regression_test_module_dirs()` / `itm_ensure_canonical_equipment_type_modules()` |
| `scripts/ensure_equipment_type_modules.php` | Verify or recreate missing canonical `modules/is_*/index.php` wrappers (CLI) |
| `scripts/cleanup_equipment_test_module_artifacts.php` | **CLI-only:** remove test `equipment_types` rows, ITM test companies, junk `is_*_itm_eqdct_*` folders, then re-ensure canonical façades |
| `scripts/equipment_delete_clear_table_test.php` | DB regression for equipment `clear_table` + transactional single delete (use type names **`Switch`** / **`Server`**, not suffixed names) |
| `scripts/employees_delete_clear_table_test.php` | DB regression for employees `clear_table` transaction rollback |
| `scripts/check_equipment_clear_table_delete.php` | Static guard for equipment clear-table helpers (smoke step 7) |
| `scripts/check_employees_clear_table_transaction.php` | Static guard for employees clear-table transaction (smoke step 6) |

**Why tests must not invent new `is_*` folder names:** inserting `equipment_types` named like `Switch itm_eqdct_*` triggers `itm_ensure_equipment_type_module_scaffold()` in `includes/ui_config.php` and pollutes the sidebar. After local DB regression runs, run `php scripts/cleanup_equipment_test_module_artifacts.php`.

**Smoke / optional DB regression:** `bash scripts/smoke_test.sh` runs the static checkers (steps 6–7). Set `SMOKE_RUN_DB_TESTS=1` to also run `employees_delete_clear_table_test.php` and `equipment_delete_clear_table_test.php` (requires MySQL).

#### 5. Pre-merge verification (scripts)

When adding or changing anything under `scripts/`:

1. Confirm a row exists in **`scripts/index.html`** (what / how / access).
2. Open the script in the **browser** (if applicable) — **← Scripts index** visible; module names use `../modules/…`; table names link only when a matching module folder exists; no phpMyAdmin links outside `scripts/index.html`.
3. Run **`php -l scripts/<changed>.php`** on touched PHP files.
4. Run the script’s CLI command once when behavior is non-trivial.

---

## 🏗 Coding Standards

### 1. Module Structure
Each module must maintain a flat structure with these specific files:
`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, and `list_all.php`.

> [!IMPORTANT]
> **Do not create Master Templates:** Do not attempt to abstract CRUD into a single master template. Each module must remain independent.

### 2. Database & Schema Rules
* **Schema Updates:** If a field/table is deleted or a header renamed, update `database.sql`.
* **Company Scoping:**
    * **Hide** `company_id` from all UI views.
    * Add safe inline FK creation logic to create referenced rows automatically.
    * Scope all queries and inserts by `company_id`.
* **Audit Logging:** The system sets MySQL session variables (`@app_user_id`) in `config.php`. Do not overwrite these.
* **Standard Fields:** Every new table in `database.sql` must include:
    * `company_id` INT NOT NULL
    * `active` TINYINT DEFAULT '1'
    * `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    * `updated_at` TIMESTAMP DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP

### 3. Protection Zone (STRICT: No Auto-Changes)
Do **not** modify logic or structure unless explicitly requested:
`/modules/equipment/`, `/modules/idfs/`, `/modules/idf_links/`, `/modules/idf_positions/`, `/modules/idf_ports/`, `/modules/audit_logs/`, `/modules/employees/`, `/modules/settings/`, `/modules/user_companies/`, `modules/employee_system_access/`, `modules/cable_colors/`, `ui_configuration`.

### 4. Dynamic UI Configuration (Settings)
Modules must read/validate settings via `itm_get_ui_configuration()`:
* **Button Positions:** Render refresh/add controls based on `new_button_position`.
* **Table Actions:** Add `data-itm-actions-origin="1"` to "Actions" headers/cells to allow the global layout engine to map `table_actions_position`.
* **DB Import Endpoint (Index Tables):** Add `data-itm-db-import-endpoint="index.php"` to every module index table so `📥Import Excel` can use the save-to-database flow.
* **Global Behaviors:** Respect system toggles for `enable_all_error_reporting`, `enable_audit_logs`, and `records_per_page`.

### 5. Standard Feature Set
Every module (excluding the Protection Zone) must implement:
* **Hide** `company_id` from all UI views.
* **Bulk Actions:** "Select to Delete" and "Clear Table" (visible if count >= `records_per_page`).
* **Search:** Comprehensive search across all visible fields.
* **Order:** Standardized sort fields ASC DESC - '▲' : '▼'.
* **Tools:** `📗Export Excel`, `📄Export PDF`, and `📥Import Excel` (linked via `js/table-tools.js`).
* **Navigation:** Standardized server-side pagination based on `records_per_page`.
* **Error Reporting:** Standardized server-side `enable_all_error_reporting` value from Settings.
* **Enable Audit Log:** `enable_audit_logs` value from Settings.
* **Audit Trail Coverage:** Mandatory INSERT/UPDATE/DELETE logging to `audit_logs` if enabled so changes are traceable in the audit center.

### 6. Empty-State Sample Data Process
* **UI:** Add "Add sample data" button at the bottom of `index.php` if the result set is empty for the active company.
* **Handler:** Implement a `POST` handler for `add_sample_data` in `index.php` that:
    * validates CSRF (`itm_require_post_csrf()`),
    * confirms there is an active `company_id`,
    * re-checks the table is empty for that `company_id` before inserting.
* **Source:** Seed rows must match `INSERT INTO` entries in `database.sql` for that module table.
* **Tenant Safety:** Always write seeded rows with active `company_id`; never expose/edit `company_id` in UI.

---

## 🔒 Security Protocol

### SQL Injection (SQLi)
1. **Prepared Statements:** ALWAYS use MySQLi prepared statements for user data.
2. **Identifier Validation:** Use `itm_is_safe_identifier($name)` for table/column names.
3. **Execution:** Use `itm_run_query($conn, $sql)` with error trapping.
4. **Audit:** Run `php scripts/check_sql_injection_coverage.php` after changes.

### CSRF & XSS
* **CSRF:** Use `itm_require_post_csrf()` in handlers. Forms require:
  `<input type="hidden" name="csrf_token" value="<?= itm_get_csrf_token() ?>">`
* **XSS:** Wrap all echoed user-provided strings in `sanitize($data)`.

---

## 💡 Development Patterns

### PHP Best Practices
* **Paths:** Use `ROOT_PATH` with a trailing slash for filesystem operations.
* **Variable Collisions:** Use unique, prefixed variables in `includes/` (e.g., `$itm_sidebar_user`).
* **Commenting:** Follow the **"Why-Focused"** style.
    * *What:* "Looping through array" (Avoid).
    * *Why:* "Human-friendly labels for UI positioning settings stored in the database." (Prioritize).

### UI/UX Requirements
* **Layout:** `.container` > `.main-content` > `.content`.
* **Hide** `company_id` from all UI views.
* **Foreign Keys in UI:** Never display raw FK numeric IDs in list/detail screens when a related label exists. Render human-readable values (e.g., `name`, `title`, `username`) instead.
* **Buttons:** `btn-primary` for main actions; `btn-sm` for table actions.
* **Tables:** Use `.itm-actions-cell` and `.itm-actions-wrap` for action columns.
* **Active field use badges for status (index.php/view.php).
* **Booleans (List View):** Use badges for status: 
    * `<span class="badge badge-success">Active</span>`
    * `<span class="badge badge-danger">Inactive</span>`
* **Booleans (Edit Mode):** Use icons: `1` = ✅, `0` = ❌.
* **Active Checkbox Guardrail:** In create/edit forms, treat `active` as a checkbox boolean for `tinyint` variants (not only `tinyint(1)`) so the Active toggle is always rendered and normalized reliably.


* **Dynamic Selects:** Enable quick-add functionality: `<option value="__add_new__">➕</option>`.
* **Color Fields:** Use color picker UI: `<input type="color" name="hex_color" id="cable-hex-color-picker" value="#008000">`.
* **Date Fields:** Show date picker UI.

---

## 🛠 Setup & Debugging
* **Dev Credentials:** `localhost` | `root` | `itmanagement`.
* **Online AI Test Environment:**
  * `https://nelsonsalvador.myddns.me` | Login: `Admin` | Password: `Admin`.
  * `http://nelsonsalvador.myddns.me/phpmyadmin/` | Database: `itmanagement` | Login: `root` | Password: (blank).
  * Note: `https://nelsonsalvador.myddns.me/phpmyadmin/` currently returns upstream TLS/certificate errors; use HTTP for phpMyAdmin checks.
* **Logs:** System errors are piped to `ROOT_PATH . 'error_log.txt'`.
* **Testing:** Browser screenshots are not supported; rely on verbose error logging.
* **CLI scripts:** Run from the repository root with **PHP 7.4.33** and **MySQLi** enabled.
  * **Linux, macOS, CI, and any host where `php` is on PATH:** `php scripts/<script>.php`
  * **Windows (Laragon) when `php` is not on PATH:** use the Laragon 7.4 binary, e.g. `<laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\<script>.php` (replace `<laragon-root>` with your install path; do not use a system PHP 8.x build that lacks MySQLi).

---

## 🧹 Change Hygiene Rules (Diff Quality)
To keep PRs reviewable and avoid noisy churn, follow these rules for every change:
* **No line-ending normalization:** Preserve existing CRLF/LF style per file. Do not rewrite whole files just to change one line.
* **No broad search/replace across modules unless explicitly requested:** Prefer targeted edits to only the files required by the task.
* **Minimize touched lines:** Keep patches surgical and avoid formatting-only edits (spacing, wrapping, reindent) when logic is unchanged.
* **Preserve file encoding and structure:** Do not change charset, BOM behavior, or module layout unless requested.
* **If a change must be bulk-applied, state why in the PR description** and confirm the scope before continuing.

### PR review (mandatory)
* **Reviews and fixes are done in-repo via **Cursor**, optional **Bugbot**, manual IDE review, and the scripts below — same intent as external P1/P2 bot comments.
* **Pre-merge review pass (required before merge):** on every PR, run a targeted review of the changed files (last N files in the diff when large) against this `AGENTS.md`, including at minimum:
  * `php -l` on every touched `.php` file.
  * `php scripts/check_sql_injection_coverage.php` when PHP/SQL changed.
  * `php scripts/check_audit_logs_coverage.php` when CRUD or audit-related paths changed.
  * `php scripts/check_database_sql_company_name_uniques.php` when `database.sql` or tenant unique keys changed.
  * FK label guardrails: no raw `*_id` / `*_by` numeric IDs on list/detail when a label exists; persisted FKs stay selected on edit forms.
  * Module consistency rechecks for any touched module (`index.php`, `view.php`, `edit.php`, `create.php`, `list_all.php`, and `delete.php` when applicable).
  * IDF-related changes: `php scripts/idfs_sync_human_test.php` (or the Laragon 7.4 path from Setup) — hard-fail if any `[FAIL]`.
  * Smoke/CI workflows in `.github/workflows/` when present; list exact commands and outcomes in the PR description (do not claim “no tests run” when checks ran).
* **CI and repo scripts stay authoritative:** smoke workflows and maintenance scripts (for example `check_audit_logs_coverage.php`, `check_database_sql_company_name_uniques.php`) are owned by the repository and must keep passing.

### GitHub PR review comments (mandatory)
* **Read all GitHub PR feedback** before considering a PR merge-ready: use `gh pr view`, `gh api repos/{owner}/{repo}/pulls/{number}/comments`, GraphQL review-thread endpoints, or the PR URL. Include human reviewers, **Bugbot**, **Codex**, and actionable CI/check annotations when present.
* **One actionable comment → one fresh Cursor chat:** for each distinct review comment or coherent thread that requests a change, **start a new forked/isolated agent chat** scoped to that item only. Do not mix unrelated review threads in the same session unless they share one root cause.
* **Implement on a fresh branch + new PR:** address the comment with code/docs changes per **NEW PR always** (commit, push, `gh pr create` when checks pass). Link the resolving PR in the GitHub reply when applicable.
* **Auto-fix when asked to address review comments:** for each actionable thread, (1) verify whether `master` already contains the fix, (2) if not, patch on a **fresh branch + new PR**, (3) run the relevant checks (`php -l` on touched files, plus any script named in the comment or PR scope), (4) post the GitHub reply below. Do not assume silence means done.
* **Always reply on GitHub with a status label:** every addressed review thread must receive an explicit reply that **starts with exactly** **`Fixed`** or **`Not Fixed`** (that spelling and capitalization). Do **not** use `Fix`, `fix`, `Won't fix`, or other variants—the label must be searchable and consistent.
  * **`Fixed`:** the concern is resolved in a merged commit or an open linked PR; state what changed (file, commit/PR number, or command run).
  * **`Not Fixed`:** intentional deferral, out of scope, or blocked; state why and cite a follow-up issue/PR if planned.
* **Do not leave actionable review threads silent:** if a comment asked for a change, respond with **`Fixed`** or **`Not Fixed`**—never only push code without a labeled GitHub reply.
* **Merged PRs still need replies:** if a PR merged with silent bot/human threads, post retroactive **`Fixed`** / **`Not Fixed`** replies on each thread after verifying `master` (or cite the PR that already fixed it).
* **AGENTS.md updates for process rules:** document new review/reply requirements in **`AGENTS.md` on a fresh branch + new PR** (do not fold into an unrelated feature PR).

#### Triage workflow (`gh` CLI)
Use this when asked to “check last N comments and reply” (Codex/Bugbot/human line comments on a PR).

1. **List recent review comments** (line comments on the diff; newest first):
   ```bash
   gh api "repos/pirica/it-management/pulls/comments?per_page=100&sort=created&direction=desc"
   ```
   Filter to bot/human reviewers (exclude your own **`Fixed`** / **`Not Fixed`** replies). For one PR only, use `pulls/{number}/comments` instead of the repo-wide endpoint.
2. **Find threads still missing a reply:** a parent comment has no child with `in_reply_to_id` equal to its `id`. Merged PRs still require retroactive replies on actionable parents.
3. **Verify `master`:** `git fetch origin master` and confirm the concern is already fixed, or open a **fresh branch + new PR** with the patch.
4. **Post a threaded reply** (body must start with **`Fixed`** or **`Not Fixed`**):
   ```bash
   gh api repos/pirica/it-management/pulls/{pr}/comments -X POST \
     -f body="Fixed — …" \
     -F in_reply_to={comment_id}
   ```
   * **`in_reply_to` must be numeric:** use `-F in_reply_to={id}` (typed field), not `-f` (string), or GitHub returns HTTP 422.
   * **PowerShell:** same flags; use two `-f` lines or `-m` for the body if quoting is awkward.
5. **After merge:** add a short follow-up reply on the same thread, e.g. **`Fixed`** — merged to `master` as PR #NNNN, when the fix landed in a follow-up PR.

**Reply templates (searchable labels):**
* **`Fixed`** — merged PR #1708; `scripts/check_ui_configuration_coverage.php` now rejects inverted `perPage >= totalRows` gates.
* **`Fixed`** — PR #1713 on `master`; `modules/system_access/index.php` tbody `ids[]` cells gated with `$showBulkActions`.
* **`Not Fixed`** — intentional per AGENTS.md § Scripts directory (phpMyAdmin linked only from `scripts/index.html`, not derived per-request host).

### Module Consistency Guardrail (Mandatory)
When a module uses duplicated procedural entry files (`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`):
* **Apply critical behavior fixes consistently** across all module entry files when they share the same helper blocks (rendering, CSRF validation, FK option loading).
* **Incomplete implementation is not acceptable:** if a fix is made in one duplicated entry file, you must recheck and apply it to all matching duplicated files before finishing.
* **Mandatory recheck checklist:** verify behavior consistency in `index.php`, `view.php`, `edit.php`, `create.php`, and `list_all.php` (plus `delete.php` when applicable) for the changed module before commit.
* **Boolean detection consistency:** If checkbox detection logic is updated (example: `active` with `tinyint` variant handling), propagate the same update to every duplicated entry file that shares create/edit rendering or POST normalization paths.
* **For display renderer updates** (for example badges/swatches/label mapping), propagate the same renderer/helper logic to both `index.php` and `view.php` before commit.
* **Verify FK label rendering in both list and detail/edit flows** (no raw FK IDs when a related label exists), including company-scoped fallback behavior where seeded reference rows may be missing for a tenant.
* **FK label guardrail (hard fail):** if a module list/view screen shows raw FK IDs such as `equipment_id=5` or `level_id=23` while a related label row exists, the task is **not complete**. You must fix label rendering and tenant-safe fallback lookup before commit.
* **Switch Status FK + color fallback guardrail (mandatory for `modules/switch_status/`):**
    * Preserve persisted FK selections when tenant-scoped option queries do not return the saved row (do not let edit forms fall back to `-- Select --` for existing values).
    * For `color_id`, keep swatch rendering resilient by resolving `hex_color` with tenant-scoped lookup first (`id` + `company_id`) and then global-by-`id` fallback for legacy/shared rows.
    * Keep duplicated entry files aligned (`index.php`, `edit.php`, `view.php`) so list/detail/edit flows all use the same FK fallback and color preview behavior.
* **Ensure FK dropdowns preserve persisted selections:** if a saved FK value is not returned by the current company-scoped options query, append/load that saved value so edit forms do not fall back to `-- Select --`.
* **Mandatory FK recheck before commit (all changed modules):**
  * Open `index.php` and `view.php` and confirm FK columns render labels (not numeric IDs).
  * Open `edit.php` and confirm persisted FK values remain selected even when company-scoped options are incomplete.
  * Confirm fallback lookup is tenant-safe (company scoped first, then id-only fallback only for preserving legacy/shared references).
* **Mandatory column + SQL relation audit (hard fail):**
  * For mandatory review requests, audit **all columns and SQL relations** for each requested module folder before code changes are finalized.
  * Validate visible-column rendering in `index.php`, `view.php`, and `list_all.php` against `database.sql`/`information_schema` relationships.
  * Replace raw foreign keys with meaningful related display values whenever possible (for example hostname, status, color name, VLAN name, device/position labels).
  * Include both declared FK constraints and relation-like `*_id` columns that may be stored without an explicit FK constraint.
  * Keep tenant-safe lookup order: `company_id` scoped lookup first, then id-only fallback only for legacy/shared references when scoped rows are missing.
  * If a related human-readable label exists but any visible screen still shows raw FK IDs, the task is not complete.
* **Created-by UX guardrail (hard fail):**
  * For fields such as `created_by`, `updated_by`, `approved_by`, and `*_by_user_id`, list/detail screens must never show raw numeric IDs when a user row exists.
  * In `create.php`/`edit.php`, these fields must render as user dropdowns (human-readable labels), not free-text numeric inputs.
  * User labels must prefer `first_name + last_name`; use `username` only as fallback when full name is empty.
  * If a persisted user ID is missing from company-scoped options, append/load the saved value so edit forms do not reset to `-- Select --`.
* **Testing/reporting guardrail (mandatory):**
  * Do not claim “No tests run” when checks were executed.
  * Minimum required checks for CRUD changes: `php -l` on touched PHP files and `php scripts/check_sql_injection_coverage.php`.
  * After employees/equipment `clear_table` changes: `php scripts/check_employees_clear_table_transaction.php`, `php scripts/check_equipment_clear_table_delete.php`; optional DB runs per catalog in `scripts/index.html` (`SMOKE_RUN_DB_TESTS=1` or run the `*_test.php` scripts directly). Run `php scripts/cleanup_equipment_test_module_artifacts.php` when equipment regression tests touched the database.
  * PR descriptions must list the exact commands that were run and their outcomes.
* **New branch + NEW PR always (mandatory — non-negotiable):**
  * **Every separate request, bugfix, or follow-up** ships on a **fresh branch** and opens a **brand-new pull request**. Do **not** add unrelated commits to an already-open PR “to save time”, and do **not** reuse **PR #N** for **new scope** after merge unless the user explicitly asked to extend that same PR while it is still open.
  * Treat **“always a NEW PR”** literally: **`gh pr create`** (or equivalent) for each deliverable; the prior merged PR is history—**next change = next PR number**.
  * Package every requested implementation in a **fresh branch** and open that **new PR** when the work is ready—do **not** wait for an explicit “please commit” (unless the user asked to hold commits or the session is exploratory/read-only).
  * When required checks pass, **commit**, **push**, and **open the PR** (`gh pr create` when available). A task is not complete with only unstaged or unpushed local changes.
  * Do not reuse a previously opened **pull request** for a **new** request, even if the files overlap.
  * Preferred status wording example: “I’m now packaging this as a fresh branch/PR (per your **NEW PR always** rule) with the root sync fixes, the human-flow regression test, and the AGENTS guardrail update.”
* **IDF synchronization guardrail (mandatory for `modules/idfs/view.php`, `modules/equipment/`, `modules/switch_ports/`, and `modules/idfs/device.php`):**
  * All **Create, Edit, Update, Delete, Copy, and Move** operations must keep the following tables fully synchronized at all times: `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links`.
  * **Hard-fail policy:** partial cross-table updates are not allowed. When a workflow touches multiple IDF-related tables, use transaction boundaries and rollback on any synchronization failure.
  * **Delete/overwrite/move safety:** cleanup/update dependent `idf_links` and `idf_ports` rows before deleting or replacing `idf_positions`, and always keep `equipment.idf_id` and `switch_ports.idf_id` aligned with the active IDF link state.
  * `link_create`, `port_update`, `link_delete`, `position_save`, `position_delete`, `position_copy`, and move/reorder actions must preserve status/color/label/notes parity across linked `idf_ports` and mirrored `switch_ports` rows.
  * **Unknown reset rule:** unlink/delete flows must reset synchronized ports to tenant `Unknown` + Gray (`#808080`) defaults where applicable.
  * **Wrapper consistency finding (switch_ports):** if `create.php`, `edit.php`, `view.php`, `delete.php`, or `list_all.php` are wrappers that route to `index.php`, `index.php` must not overwrite wrapper-provided `$crud_action` values.
  * **High-Density Support:** Rack position validation supports up to **250** positions. Batch updates (move/reorder) use temporary offsets (1000) to avoid unique constraint collisions.
  * **Mandatory human-flow testing for every affected workflow:** execute human-flow regression for each changed Create/Edit/Update/Delete/Copy/Move path before PR.
    * Required regression command (from repository root):
      * **Default (Linux, macOS, CI, PATH):** `php scripts/idfs_sync_human_test.php`
      * **Windows Laragon fallback (when `php` is not on PATH):** `<laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\idfs_sync_human_test.php`
    * If any workflow or command run reports `[FAIL]`, the task is not complete.
* **Before commit, smoke-check all three screens at minimum:** list (`index.php`), detail (`view.php`), and edit (`edit.php`) for the changed module.
* **Wrapper action routing guardrail (mandatory):** for modules that use wrapper entry files (`create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) to set `$crud_action` before requiring `index.php`, verify `index.php` does not overwrite wrapper-provided values. Confirm each wrapper still routes to its expected screen/handler before creating a PR.
* **API:**
   * `scripts/api.php` needs to be updated if any changes on the project.
* ** Implement the missing JSON import endpoint:**
   * For modules/*/index.php, so 📥Import Excel now handles table-tools.js save-to-database requests instead of falling through to normal page rendering (which caused the generic “Import failed while saving to database.” error).
