# AGENTS.md

> [!IMPORTANT]
> **Role:** You are a Senior PHP Developer maintaining a legacy-style Procedural IT Management System.
> **Constraint:** Follow these rules strictly. Do not refactor to OOP, MVC, or modern frameworks. Keep logic flat and modular.

This document provides essential instructions, architectural constraints, and coding standards for AI agents working on the **IT Management System**.

## ✅ Agent compliance workflow (mandatory)

Before making any change, replying, running commands, editing files, or proposing solutions:

1. **Read `AGENTS.md` completely** at session start (and when resuming after a long gap or context switch).
2. **Read `scripts/SCRIPTS.md` completely** at session start and **again before any reply** that adds, changes, runs, or documents anything under `scripts/` (catalog entries, QA runners, smoke checks, audit tools, or new CLI/browser scripts). For scripts topics, **`scripts/SCRIPTS.md` is authoritative** — do not rely on stale copies elsewhere.
3. **Stop and ask clarification questions** if any part of `AGENTS.md` or `scripts/SCRIPTS.md` (when in scope) is unclear, ambiguous, missing, conflicting, or not fully understood — do not guess or proceed on assumptions.
4. **Pre-implementation discovery (mandatory — no code yet):** do **not** begin implementation — editing files, running mutating commands, or proposing concrete patches — until you have produced, for the task scope:
   - **Architectural map** — folders, entry files, shared helpers (`includes/`, `config/`, `js/`), scripts, and DB/API boundaries the work touches.
   - **Module summary** — purpose, business rules, protection-zone status, and relevant facts from each affected folder's `AGENT_NOTES.md` (read those files first).
   - **Dependency analysis** — FKs and table relations, tenant scoping (`company_id`), shared UI/JS contracts, audit paths, and downstream consumers that must stay aligned.

   State the map, summary, and analysis in the agent reply before the first implementation step (or note explicitly when resuming the same scoped task and the analysis is unchanged). Exceptions: read-only or exploratory sessions; documentation-only deliverables when editing `AGENTS.md` / `scripts/SCRIPTS.md` is the whole task; or a user request already limited to one known file with no cross-module impact.
5. **Always update all in-scope documentation (hard fail):** in the **same PR** as the code change, update **every** doc the deliverable touches — not only the nearest `AGENT_NOTES.md`. Stale cross-references are a **hard fail**.

   | If the change affects… | Update (same PR) |
   |------------------------|------------------|
   | Repo-wide rules, Explorer/upload hardening, `.htaccess` policies, agent workflow | **`AGENTS.md`** |
   | Scripts, backfill runners, regression/PoC tools | **`scripts/SCRIPTS.md`** and **`scripts/scripts.php`** (when adding/changing scripts) |
   | Upload storage paths, managed `.htaccess` bodies, module → folder map | **`docs/file_upload_modules.md`** |
   | Module or folder behaviour | Matching **`AGENT_NOTES.md`** (see **Directory Map → AGENT_NOTES.md**) |
   | New or renamed canonical doc under `docs/` | **`docs/AGENT_NOTES.md`** |

   **No numbered PR cites in documentation or comments (hard fail):** describe current behaviour, files, and commands. The following are **forbidden** in all docs and comments:

   - Any explicit pull request reference with digits, such as:
     - `PR #<digits>`, `PR#<digits>`, `PR  #  <digits>`
     - GitHub URLs containing `/pull/<digits>` (for example: `https://github.com/owner/repo/pull/<digits>`)
     - Phrases like “post PR #<digits>”, “merged in PR #<digits>”, or similar history keyed to a numbered PR.

   These must **not** appear in:

   - `AGENT_NOTES.md`, `docs/`, module notes, or feature sections of **`AGENTS.md`**, **`scripts/SCRIPTS.md`**, or **`scripts/scripts.php`** catalog prose
   - **Code comments** and file headers (`// Why:`, `/** … */`) — **keep the comment**; remove only the numbered PR cite (for example, write “added manually across companies”, not “PR #<digits> added …”).

   **Allowed (`AGENTS.md` only):** a single generic placeholder that refers to PRs without digits:

   - The **literal** placeholder `PR #N` (uppercase `PR`, one space, `#`, uppercase `N`).
   - Generic phrases like “fresh PR” or “do not push to an open PR”.

 Digits are **never** allowed inside the placeholder — only the literal `PR #N` is valid. No `https://…/pull/<digits>` links with numeric PR IDs are allowed outside this rule text.

   **When editing stale text:** strip the number/URL; do not delete the surrounding explanation.

   Ship on a **fresh branch + new PR**; do not fold unrelated feature work into the same PR (see **Change Hygiene → PR review**).
6. **Always create and update `AGENT_NOTES.md` (hard fail):** for every in-scope folder you read or change, **read** that folder's `AGENT_NOTES.md` first (and the parent folder's file when editing a subfolder). **Create** the file from `templates/AGENT_NOTES.md` when it is missing. **Update** it in the **same PR** whenever your work changes purpose, tables, FKs, business rules, UI behaviour, API actions, file layout, tenant rules, audit coverage, or known pitfalls. Do not mark a deliverable complete while notes are missing, empty, or stale for a folder you touched.
7. **Before every reply**, re-check `AGENTS.md` and, when the task touches `scripts/`, **`scripts/SCRIPTS.md`**, and confirm the response follows them (pre-implementation discovery, architecture, Protection Zone, encoding, scripts catalog, testing guardrails, PR workflow, and any section relevant to the task).
8. **Auto-open fresh PRs (mandatory):** when implementation is complete and required checks pass, ship via **FRESH PR only**: **`git checkout -b <new-branch>`** from synced **`origin/master`** → commit → **one** **`git push -u origin <new-branch>`** (first publish only) → **`gh pr create`** → reply with the **new PR URL**. **Do not ask** the user to confirm (“say so and I will…”, “would you like me to open a PR?”, etc.). There is **no** push to update PR #N. Exceptions: user explicitly asked to hold commits/push, read-only/exploratory session, or no file changes to commit.
9. **Never push to an existing PR branch (hard fail):** agents **do not** `git push` (including `git push`, `git push origin <branch>`, or force-push) to any branch that already has **open or merged PR #N** for this work. Follow-ups, review fixes, and corrections use a **new branch name** + **new push** + **`gh pr create`** → **new PR number**. Forbidden user-facing lines: “Pushed to an existing PR URL”, “updated the open PR”, “added commits to the open PR”, “you can push after the diff check”.
10. **Never local-only commits (hard fail):** `git commit` without the **first-publish** **`git push -u origin <new-branch>`** and **`gh pr create`** (when there are file changes to ship) is **not done**. Do **not** tell the user work is “committed” if `git status` shows **ahead of origin** or the PR URL is missing. Do **not** suggest “push when you want” — **you** complete the fresh-PR sequence in the same turn.
11. **Pre-push full diff review (hard fail):** before the **only** allowed **`git push -u origin <new-branch>`** on that deliverable, run and **read** the full patch against `origin/master` (see **Change Hygiene → Pre-push diff review**). Do **not** publish a branch whose diff removes unrelated functions, reverts a just-merged fix, or shows large deletions you did not intend.

Only after `AGENTS.md` and (when in scope) `scripts/SCRIPTS.md` have been checked, understood, the **pre-implementation discovery** gate (step 4) is satisfied, and in-scope docs are updated when required may you continue with implementation.

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

## 🧩 Character encoding, locales, and Unicode (mandatory)

This project stores and displays **Unicode** text (including emoji such as 🧩) end-to-end. Do not “fix” mojibake by removing punctuation or emoji—fix the **encoding contract**.

### Database (`database.sql`)

* Database and tables use **`utf8mb4`** with **`utf8mb4_unicode_ci`** (see top of `database.sql`: `CREATE DATABASE … utf8mb4`, `SET NAMES utf8mb4`).
* Application connection must match: `mysqli_set_charset($conn, 'utf8mb4')` in `config/config.php`.
* Never downgrade to `utf8` (3-byte) for new tables—emoji and some symbols need 4-byte UTF-8.

### Repository and source files

* **All** tracked text (`.php`, `.md`, `.sql`, `.js`, `.css`, `.html`) is **UTF-8 without BOM**, except generated QA artifacts below.
* **`.editorconfig`** and **`.gitattributes`** at repo root enforce UTF-8 and LF for agents and editors—do not re-save as Windows-1252 / “ANSI”.
* **Change hygiene:** do not rewrite whole files to change encoding or line endings; see **Change Hygiene Rules**.

### Locales and copy

* **UK English (en-GB)** is the default for UI labels, docs, and agent-written prose (spelling: organisation, colour only when matching existing UI).
* **Dates (display and import):** use **dd/mm/yyyy** in UI lists, views, and Excel import parsing. MySQL storage stays `Y-m-d` / `Y-m-d H:i:s`. Shared helpers: `includes/itm_date_format.php` (`itm_parse_date_input`, `itm_format_date_display`, `itm_format_cell_scalar_display`). Maintenance: `php scripts/apply_date_display_format.php` when new flattened CRUD modules omit the `cr_render_cell_value()` hook.
* **Portuguese (Portugal) (pt-PT)** is used where the product already ships bilingual or regional copy—match existing tone; do not machine-translate unrelated modules in drive-by edits.
* **Emoji** in UI, `AGENTS.md`, and seed data are allowed when intentional (e.g. 🧩 section markers, toolbar icons in copy).

### HTTP and PHP output

* HTML responses: `Content-Type: …; charset=utf-8` (see `config/config.php` JSON headers and script browser pages).
* `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')` for echoed user data.
* JSON from scripts: `json_encode(…, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` so Portuguese accents and symbols are not `\u` escaped unnecessarily.

### Generated QA reports (`qa-reports/`)

* Runner/build-report behaviour, commands, and UTF-8 write helpers: **`scripts/SCRIPTS.md`** (Full-module browser QA; shared libraries).
* **`.md` under `qa-reports/`** may be written **with a UTF-8 BOM** (`EF BB BF`) so **Windows Notepad** opens them correctly.
* **`.json` under `qa-reports/`** is UTF-8 **without BOM** — `json_decode()` rejects a leading BOM; the report builder strips BOM on read if an older file still has one.

### Mojibake troubleshooting (symptom → cause)

| What you see | What it usually means |
|---|---|
| `â€”` instead of `—` | UTF-8 em dash bytes read as Windows-1252 / Latin-1 |
| `â€¦` instead of `…` | UTF-8 ellipsis bytes read as Windows-1252 |
| `Ã©` instead of `é` | UTF-8 accent read as Latin-1 (common in pt-PT strings) |
| `ðŸ§©` instead of 🧩 | UTF-8 emoji read as Latin-1 |

**The file on disk is often already correct UTF-8** (verify with a UTF-8-aware editor or hex: em dash = `E2 80 94`). Fix the **viewer**, not the database:

1. **Cursor / VS Code:** `"files.encoding": "utf8"`; reopen file with encoding **UTF-8**.
2. **PowerShell:** `Get-Content -Encoding utf8 path\to\file.md` (default encoding is not UTF-8 on Windows).
3. **Re-build QA markdown** per **`scripts/SCRIPTS.md`** (Full-module browser QA) after pulling encoding fixes.
4. **Do not** use Notepad “Save as” ANSI or Excel “CSV ANSI” on UTF-8 exports.

**Agents:** never replace `—` / `…` / emoji with ASCII substitutes just to avoid display glitches in one tool; preserve UTF-8 and document viewer settings.

### PR descriptions: shell / quoting corruption (not repo encoding)

GitHub PR titles and bodies are often pasted from the shell (`gh pr create`, `gh pr edit`). On **Windows PowerShell**, inline `--body "..."` strings can **mangle** backticks, `$variables`, and step names before GitHub ever sees them. That is **shell/quoting corruption**, not a UTF-8 or repository encoding defect — do not “fix” it by re-encoding `AGENTS.md` or source files.

| What you see on GitHub | What it usually means |
|---|---|
| `\dd` or `^Gdd` instead of `add` | PowerShell ate or escaped backticks around `add` |
| `\ulk_delete` instead of `bulk_delete` | Leading `b` dropped with broken `` ` `` escaping |
| `\\ >= \\` instead of `$totalRows >= $perPage` | `$` expanded or stripped in the shell |
| Doubled backslashes before step slugs (`\list\`) | Escaped markdown backticks in a quoted string |

**Mandatory workflow (agents):**

1. Write the PR body to a **UTF-8** file in the repo (e.g. `.pr-body-tmp.md` — do not commit; add to `.gitignore` if the file is kept locally).
2. Create or update the PR with **`--body-file`**, not inline `--body`:
   ```bash
   gh pr create --title "Short title" --body-file .pr-body-tmp.md
   gh pr edit <number> --body-file .pr-body-tmp.md
   ```
3. **Verify on GitHub** before marking the PR ready: `gh pr view <number> --json body` and open the PR in the browser — step slugs (`add`, `bulk_delete`, `bulk_cancel`, `list`) and PHP snippets must read literally.
4. If the body is wrong, **`gh pr edit --body-file`** again; do not stack “fixes” as extra broken inline `--body` strings.

On **bash**, prefer a heredoc or `--body-file` when the body contains `` ` ``, `$`, or `>=`. Treat garbled PR text as a **process** bug until verified with step 3.

## 📂 Directory Map
* `config/`: Core settings and `config.php`.
* `includes/`: UI components (headers, sidebars) and utility functions.
* `modules/`: Feature-specific CRUD logic.
* `scripts/`: Maintenance, security audits, and CLI tools. Standards: `scripts/SCRIPTS.md`. Catalog: `scripts/scripts.php`.
* `js/` & `css/`: Assets (use `css/styles.css`).
* **Required Dirs:** `images/`, `tickets_photos/`, `backups/`, and `files/` must exist with write permissions.
* `scripts/api.php`: API Documentation

### AGENT_NOTES.md (mandatory per folder — always create and update)

Every **project folder that contains source code or agent-relevant assets** must include an **`AGENT_NOTES.md`** file. Use **`templates/AGENT_NOTES.md`** as the canonical section outline (sections 1–12).

| Scope | Requirement |
|--------|-------------|
| **Repo root** | `AGENT_NOTES.md` — whole-system context; read with `AGENTS.md` at session start. |
| **Top-level dirs** | `config/`, `includes/`, `modules/`, `scripts/`, `phpunit/`, `css/`, `js/`, `.github/`, etc. |
| **Each module** | `modules/<slug>/AGENT_NOTES.md` — purpose, tables, FKs, business rules, UI, pitfalls. |
| **Code subfolders** | e.g. `scripts/lib/`, `modules/*/api/`, `modules/*/includes/` — document that subfolder's role. |
| **Test mirrors** | `phpunit/tests/Unit/.../AGENT_NOTES.md` — what is tested and which module it maps to. |

**Exemptions (no `AGENT_NOTES.md` required):** runtime tenant upload trees under `files/{company_id}/**`, and other directories created only at runtime (user uploads, QA temp files).

**Mandatory agent contract (read → create → update):**

1. **Read** — before editing any in-scope folder, read that folder's `AGENT_NOTES.md` (and the parent's file when the folder is a subfolder).
2. **Create** — when adding a new in-scope folder, or when audit finds a gap, add `AGENT_NOTES.md` in the **same PR** using section headings from `templates/AGENT_NOTES.md` and facts from `database.sql`, module PHP, and `scripts/scripts.php`. Do **not** bulk-generate module notes without reading the code — the template forbids blind automation for `modules/`.
3. **Update** — whenever a change alters purpose, tables, relationships, business rules, UI behaviour, API actions, file layout, multi-tenant rules, audit requirements, or pitfalls, **update the matching `AGENT_NOTES.md` in the same deliverable**. Stale or missing notes are a **hard fail** for that task.

**Completion gate:** code changes under a folder are not done until its `AGENT_NOTES.md` exists and reflects the current behaviour.

### Scripts directory (`scripts/`) — see `scripts/SCRIPTS.md`

**Canonical source:** All rules for creating, cataloging, running, and verifying tools under `scripts/` live in **`scripts/SCRIPTS.md`**. Do not duplicate scripts standards in this file. When updating scripts-related rules, change **only** `scripts/SCRIPTS.md` and keep this section as a short pointer.

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
`/modules/equipment/`, `/modules/idfs/`, `/modules/idf_links/`, `/modules/idf_positions/`, `/modules/idf_ports/`, `/modules/audit_logs/`, `/modules/employees/, /modules/contacts/`, `/modules/settings/`, `/modules/user_companies/`, `modules/employee_system_access/`, `modules/cable_colors/`, `ui_configuration`.

### 4. Dynamic UI Configuration (Settings)
Modules must read/validate settings via `itm_get_ui_configuration()`:
* **Button Positions:** Render refresh/add controls based on `new_button_position`.
* **Table Actions:** Add **`class="itm-actions-cell"`** and **`data-itm-actions-origin="1"`** to Actions headers and body cells so the global layout engine can map `table_actions_position` (`js/ui-layout.js`). Module browser QA **`ui_check`** step fails when an Actions column renders without both markers on the header (and on body cells when data rows exist).
* **DB Import Endpoint (Index Tables):** Add `data-itm-db-import-endpoint="index.php"` to every module index table so `📥Import Excel` can use the save-to-database flow.
* **Global Behaviors:** Respect system toggles for `enable_all_error_reporting`, `enable_audit_logs`, and `records_per_page`.
* **API keys and rate limits:** See **API keys and rate limits (mandatory)** below.

### 5. Standard Feature Set
Every module (excluding the Protection Zone) must implement:
* **Hide** `company_id` from all UI views.
* **Bulk Actions:** "Select to Delete" and "Clear Table" (visible when row count >= `records_per_page`). Use shared **`js/bulk-delete-selection.js`** (loaded from `includes/header.php`) — see **Bulk delete toolbar and Cancel button** below.
* **Search:** Comprehensive search across all visible fields — see **List/search visible columns** below.
* **Order:** Standardized sort fields ASC DESC - '▲' : '▼'.
* **Tools:** `📗Export Excel`, `📄Export PDF`, and `📥Import Excel` (linked via `js/table-tools.js`).
    * **Disabling Exports:** Automatically generated export buttons can be hidden by adding `data-itm-no-export-excel="1"` or `data-itm-no-export-pdf="1"` to the `<table>` element or its parent `.card`.
* **Navigation:** Standardized server-side pagination based on `records_per_page`. List **Previous** / **Next** controls use link text `Previous` and `Next` (preserve `search`, `sort`, `dir`, and `page` query params). **Required `title` attributes:** `title="◀️ Previous"` and `title="▶️ Next"` on pagination anchors (not `🔎 Search`). Pagination URLs include `search=`; `includes/header.php` auto-tooltips must match **visible link text** for Next/Previous and must not treat `search=` in `href` as a Search action. **QA (`pagination` step, after `add`):** when rows > `records_per_page`, verify server HTML on page 1 includes **Next** (`btn-sm`, `page=2`, `title="▶️ Next"`), then page 2 includes **Previous** (`btn-sm`, `page=1`, `title="◀️ Previous"`) — `index.php?search=&sort=id&dir=DESC&page=1` then `page=2`.
* **Error Reporting:** Standardized server-side `enable_all_error_reporting` value from Settings.
* **Enable Audit Log:** `enable_audit_logs` value from Settings.
* **Audit Trail Coverage:** Mandatory INSERT/UPDATE/DELETE logging to `audit_logs` if enabled so changes are traceable in the audit center.

#### API keys and rate limits (mandatory)

Per-user integration keys and hourly quotas live on **`ui_configuration`**. Logic: **`includes/itm_api_rate_limit.php`** (loaded from `config/config.php`).

1. **Tier caps (rolling hour):**

| Tier | Hourly limit | API key | Session (`PHPSESSID`) |
|------|----------------|---------|------------------------|
| Free | No limit | **Not required** | **Required** — `$_SESSION['company_id']` + `$_SESSION['user_id']` via `itm_api_resolve_rate_limit_row()` |
| Basic | 300 | Required (`X-API-Key` or `api_key`) | Optional when API key present |
| Pro | 1000 | Required | Optional when API key present |
| Enterprise | 10000 | Required | Optional when API key present |

**Free is not anonymous:** a keyless request without a signed-in session returns `401` (same probe and enforce paths).

2. **Settings → API Access** (`modules/settings/`): **Free** tier hides save/generate API key controls (copy explains session-based access). **Paid** tiers may save/generate `api_key`. `tier` is a **blocked** `<select>`; counters are read-only. Do not accept `tier` from POST.

3. **Enforcement:** `itm_api_enforce_rate_limit_or_exit($conn)` resolves via API key **or** (Free only) authenticated session. Paid tiers without a key return `401`. Helpers: `itm_api_tier_requires_api_key()`, `itm_api_lookup_configuration_by_user()`, `itm_api_build_rate_limit_probe_payload()`.

4. **Quota probe (does not consume a request):** `GET scripts/api.php?rate_limit=1` returns JSON. `ITM_API_RATE_LIMIT_PROBE` skips the **login.php redirect** only — it does **not** remove the Free-tier session requirement. Free may omit `api_key` when `PHPSESSID` carries `company_id` + `user_id`; paid tiers must send a key. Response includes `api_key_required` (boolean).

5. **Regression scripts** (`scripts/SCRIPTS.md`, catalog `scripts/scripts.php`):

| Script | Expectation |
|--------|-------------|
| `php scripts/apitest_tier_free.php` | Empty `api_key`; in-process session resolve; unlimited consumes; HTTP probe via `itm_apitest_publish_http_session()` + keyless URL; `api_key_required=false` |
| `php scripts/apitest_tier_basic.php` | Basic at cap − 1; allow then block; HTTP probe requires `api_key` |

6. **PHPUnit:** `phpunit/tests/Unit/Includes/ApiRateLimitTest.php` (`itm_api_tier_requires_api_key`, probe payload).

#### Rack Planner price source sync (mandatory)

When Rack Planner stores a priced device with code `catalog:<id>`, `equipment:<id>`, or `idf_unlinked:<token>`, price changes must persist to source tables as part of save/autosave:

1. `catalog:<id>` -> update `catalogs.price`.
2. `equipment:<id>` -> update `equipment.purchase_cost`.
3. `idf_unlinked:<token>` -> update `idf_positions.price` for matching token-style `equipment_id` (`^[0-9]{4}-[0-9]{4}$`) in the active company.

Do not keep price edits only inside `rack_planner.layout_json`; source tables must remain aligned.

#### Explorer module (mandatory)

The explorer module (`modules/explorer/`) provides a secure, multi-tenant file system.

1. **Storage:** Anchored at `files/{company_id}/` and subdivided into `Common/` (all company users), `Departments/{dept_id}/` (department members only), `Private/{username}_{user_id}/` (owner only), and `Trash/` (soft-delete paths mirroring live layout).
2. **Access control (API):**
    - Segment-boundary checks in `get_full_path()` — normalize backslashes to `/`, trim slashes, block `..`.
    - Users may access `Common`, their `Departments/{dept_id}`, and their own `Private/{username}_{user_id}` only.
    - **API blocks `Private` and `Departments` roots** (`get_full_path` returns null). Prevents ZIP/list leaks across users or departments. UI **must** use `resolveScopedFolderPath()` in `index.php` to open `Private/{username}_{user_id}` and `Departments/{dept_id}` (sidebar, double-click, favourites).
    - Creation or upload of items is blocked directly in `Home` (root), `Private` root, and `Departments` root.
    - **Trash ACL:** `listRecycle`, `restore`, and `emptyRecycle` apply the same `get_full_path` rules as live storage.
3. **Protected folders:** Top-level `Common`, `Departments`, `Private`, `Trash`, and items directly under `Private`/`Departments` roots cannot be renamed, moved, deleted, copied, or zipped. The user's primary private folder (`Private/{username}_{user_id}`) cannot be renamed, moved, or deleted.
4. **Localisation:** Use UK English (en-GB) for all UI labels (e.g., 'Favourites', 'Trash').
5. **Upload hardening (`deny_http`):** Every folder under `files/` must be created with `itm_ensure_files_storage_directory()` (or `itm_ensure_upload_directory_chain(…, 'deny_http', itm_files_storage_root())`), which **force-writes** on **each path segment** (`files/`, `files/{company_id}/`, `Private/`, `{username}_{user_id}/`, `Trash/`, leaf folders, etc.):
    - **`.htaccess`** — canonical `deny_http` body from `itm_upload_directory_policy_body('deny_http')` (always overwritten). See **Upload directory hardening → Managed `.htaccess` policies** and **`docs/file_upload_modules.md`**.
    - **`index.html`** — empty placeholder from `itm_upload_directory_empty_index_html()` (always overwritten; **required on every folder segment**, not only leaves).
    Do **not** use bare `mkdir()` for tenant file trees. Serve `/files/` assets in UI through `itm_files_serve_url()` → `modules/explorer/file.php` (direct `../../files/…` URLs break after `deny_http`). Block dotfile uploads (e.g. `.htaccess`) in Explorer — managed `.htaccess` is restored on every ensure. See `docs/file_upload_modules.md` and `scripts/ensure_files_htaccess_chain.php` for backfill.
6. **Regression scripts:** `php scripts/test_explorer_paths.php` (path ACL logic); `php scripts/verify_explorer_zip_leak.php` (root ZIP blocked). PoC for malicious `.htaccess` upload: `verify_explorer_rce_htaccess.php`, `verify_explorer_rce_marker.php` (catalog in `scripts/scripts.php`).

#### Org Chart and Hierarchy (mandatory)

The org chart module (`modules/org_chart/`) provides a visual representation of the company hierarchy.

1. **Data Source:** Reporting lines are stored in the `employees.reports_to` column (self-referencing FK).
2. **Cycle Detection:** Hierarchy updates (drag-and-drop) must perform recursive cycle detection to prevent circular reporting loops.
3. **Layout:** The module uses a dynamic tree layout algorithm to position nodes automatically based on reporting lines.
4. **Persistence:** Changes to the hierarchy via drag-and-drop are saved immediately via AJAX to the `employees` table.

#### Visitors Access Log (mandatory)

The visitors access log module (`modules/visitors_access_log/`) provides a way to track manual entry logs of visitors.

1. **Quick Add:** The index view features a persistent first row for quick logging of new visitors.
2. **Security & Immutability:** Historical records (not created today) are locked for editing and deletion to maintain audit integrity.
3. **Fallbacks:** The `val_is_today` helper must fall back to `created_at` if `date_time_in` is not provided.
4. **Layout:** The module follows the action-wrapper pattern and includes standard sidebar and header components.

#### Employees module import logic (mandatory)

The employees module (`modules/employees/`) provides specialized import logic for handling various corporate data formats.

1. **Header Aliases:** The import must handle industry-specific and export-specific headers such as 'Hilton ID' (maps to `external_id`), 'Position Title' (maps to `employee_position_id`), 'Department Name' (maps to `department_id`), and sorting markers like 'Id▼' (maps to `id`).
2. **ID-based Updates:** If an 'id' column is present in the import data, the system must attempt to update the existing record rather than creating a duplicate.
3. **Automated Entity Resolution:**
    - **Departments:** If a department name is provided but not found, it must be automatically created for the company.
    - **Positions:** If a position title is provided but not found, it must be automatically created and linked to the resolved department.
4. **Email Classification:** The system must automatically classify emails. Known personal domains (e.g., gmail.com, yahoo.com) route to `personal_email`; others route to `work_email`.
5. **Boolean Normalization:** Support for common export markers is mandatory. Convert '✅' or 'Active' to `1` and '❌' to `0` for boolean fields like `on_contacts` and `on_orgchart`.

#### Backup Tape Log (mandatory)

The backup tape log module (`modules/backup_tape_log/`) provides a monthly grid to track server backups.

1. **Monthly Grid:** The index view renders one row for every day of the selected month/year/server combination.
2. **Auto-population:** The `log_date` and `tape_to_be_used` (day name) are automatically derived for each row. Sundays must be highlighted in yellow.
3. **Immutability:** Records not from today are locked for editing and deletion.
4. **Restricted Fields:** The `tape_used_for_restore` and `ism_review` fields are only editable by Admin users or staff assigned to the IT department.
5. **Exports:** XLSX and PDF exports must include the custom header (Year, Month, Company, Server, Unit No) and follow the requested grid layout.

#### Ops Report (mandatory)

The ops report module (`modules/ops_report/`) provides a daily hotel operations report stored per `company_id` and `report_date`.

1. **Day / month / year selectors:** `index.php` uses `day`, `month`, and `year` query params to load one report date.
2. **Auto-create:** `opr_ensure_report()` inserts the daily header plus default F&B outlet and walk-round rows when the date is first opened.
3. **Edit lock (D-2):** non-admins may edit **today and yesterday** only (`report_date > date('Y-m-d', strtotime('-2 days'))`); older dates are read-only unless `itm_is_admin()`.
4. **All cells editable** when the date is unlocked — no per-field role restrictions; any user may add extra rows (courtesy calls, guest experience, butler, night shift, F&B outlets, walk-round) and edit all cells on unlocked dates.
5. **UI copy in DB:** section titles, field labels, table headers, toolbar button text, and `titles.*` (browser tab, export sheet/file prefix) persist in `ops_report.report_ui_json` (inline blur-save). **Exceptions:** the date suffix (`d.m.y` from selectors) and `Company:` + `companies.company` are not stored in `report_ui_json`.
6. **Exports:** XLSX and PDF must include company header and the full report sections (duty managers, figures & revenue, F&B, walk-round, guest experience, courtesy calls, butler, night shift rows).
7. **Regression scripts** (`scripts/SCRIPTS.md`, catalog `scripts/scripts.php`): `php scripts/verify_ops_report.php` — D-2 lock, CRUD, cascade delete, registry row, `report_ui_json` seed.

#### Calendar, Alerts and Events integration (mandatory)

The calendar module (`modules/calendar/`) provides a centralized view of time-sensitive data.

1. **Integration (Sync):** The calendar must automatically pull and display:
    - Alerts from the `alerts` module. (only with `end_datetime`)
    - Events from the `events` module.
    - Tickets with a `due_date` (displayed as tasks).
    - Equipment with a `certificate_expiry`.
    - Equipment with a `warranty_expiry`.
2. **Standard CRUD:** The `events` and `event_categories` modules must follow the standard CRUD structure and multi-tenancy rules.
3. **UI:** The calendar grid must follow a Monday to Sunday layout.


#### Alerts module (mandatory)

The Alerts module (`modules/alerts/`) handles global alerts when `assigned_to_user_id` is NULL and private alerts visible only to the assigned user and the creator.

1. **Visibility Logic:**
    - **Global Alerts:** `assigned_to_user_id IS NULL`. These are visible to all users within the same company.
    - **Private Alerts:** `assigned_to_user_id = $logged_user_id`. These are visible only to the assigned user and the creator.
    - `$assigned_to_user_id = $logged_user_id AND $created_by_user_id = $logged_user_id`
2. **ICS Import:** Supports importing events from ICS files.
3. **Multi-tenancy:** Strictly scoped by `company_id`.

#### Bookmarks module (mandatory)

The Bookmarks module provides a hierarchical management system for links, featuring:
- **Privacy Scoping:** Data is filtered by `user_id` for private bookmarks and `company_id` for shared ones.
- **Dual-Pane UI:** A left sidebar with an emoji-enhanced folder tree (📁/📂) and a main list view.
- **Drag-and-Drop:** Folders can be reordered or reparented via drag-and-drop interactions.
- **Import/Export:** Supports standard browser HTML bookmark files, CSV, and XLSX exports.
- **Permissions:** Shared bookmarks are read-only for regular users, while admins and creators retain full CRUD access.

#### Company module access (mandatory)

The `company_module_access` module lets administrators (`itm_is_admin()`) enable or disable modules per company.

1. **Tables:** `modules_registry` (global slug catalog; optional `icon` seed) and `company_module_access` (`company_id`, `module_id`, `enabled`, optional company-default `icon`).
2. **Opt-out policy:** No row or `enabled = 1` allows access; only an explicit `enabled = 0` row denies access for that company.
3. **Helpers:** `includes/itm_company_module_access.php` — `has_module_access()`, `get_company_modules()`, `itm_list_all_modules_registry()`, `itm_enforce_module_access_or_exit()`.
4. **Central enforcement:** `config/config.php` calls `itm_enforce_module_access_or_exit()` after `company_id` is set — individual module entry files do not need duplicated guards.
5. **Navigation:** `includes/sidebar.php` and `dashboard.php` hide disabled modules; `modules/calendar/index.php` skips integrated sources when the parent module is disabled.
6. **Admin matrix:** `modules/company_module_access/index.php` lists **all** registry rows (including hidden, inactive, sidebar-excluded, and system modules) with AJAX toggles and bulk Select All / Cancel Select / Unselect All controls.
7. **System modules:** `settings` is always available; other system slugs remain available to admins even when disabled for a company.
8. **Relationship to `role_module_permissions`:** Company access is the first gate; role CRUD permissions remain a separate layer.
9. **Regression scripts:** `php scripts/sync_modules_registry.php`, `php scripts/verify_company_module_access.php`.
10. **Sidebar emoji precedence:** Settings per-user `module_icon_overrides` on `ui_configuration` → `company_module_access.icon` (matrix) → `modules_registry.icon` → `itm_sidebar_item_catalog()` fallback. `includes/sidebar.php` renders labels via `itm_resolve_module_sidebar_label()`; icons stay separate from `module_name` for stable matrix sort (`module_slug ASC`).
11. **Sidebar discovery from registry:** active `modules_registry` rows are merged into `itm_sidebar_structure()` for SideMenu and the live sidebar without requiring `modules/{slug}/index.php`. Enable the module per company in the matrix (`company_module_access.enabled = 1`) for non-admin users. Opening the link still needs a real module folder for CRUD.
12. **Sidebar discovery paths (all auto-register):** `itm_sidebar_structure()` discovers modules from (a) `modules/{slug}/index.php`, (b) new MySQL tables via `SHOW TABLES` + `itm_auto_create_module_scaffold()`, and (c) active `modules_registry` rows. `itm_ensure_registry_rows_for_module_slugs()` upserts missing `modules_registry` + `company_module_access` rows during discovery so new tables and folders appear in the live sidebar without visiting Company Module Access or running sync first.

#### Bulk delete toolbar and Cancel button (mandatory)

Standard index markup (inside the list card, above the search row). `department-bulk-form` is the legacy id for `modules/departments/` only; all other modules use `bulk-delete-form`.

```html
<div class="card" style="margin-bottom:16px;">
    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
        <input type="hidden" name="csrf_token" value="...">
        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger"
            onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
    </form>
</div>
```

| Control | Server HTML | Runtime (`js/bulk-delete-selection.js`) |
|--------|---------------|------------------------------------------|
| **Select to Delete** | `button[name="bulk_action"][value="bulk_delete"]` — initial label **Select to Delete** (or module-specific text; JS reads it on load) | First click: `preventDefault`, show row `ids[]` checkboxes + header select-all, relabel toggle to **Delete Selected**, show **Cancel** |
| **Cancel** | Optional in PHP; if omitted, JS injects `button[type="button"][data-itm-bulk-cancel="1"]` immediately after the bulk_delete toggle | `type="button"` only — must **not** submit the form. Click runs `exitSelectionMode()`: hides checkboxes, restores **Select to Delete** label, hides Cancel, clears checks — **no POST** |
| **Delete Selected** | Same submit button as Select to Delete (label changes in JS) | Second click on toggle: confirm + POST `delete.php` with selected `ids[]` |
| **Clear Table** | Separate `bulk_action=clear_table` submit (with confirm) | Unaffected by selection mode |

**Hard rules:**

1. **Shared script only** — include `bulk-delete-selection.js` via `includes/header.php`. Do **not** copy inline `let selectionMode = false` handlers into module `index.php` files.
2. **Cancel never submits** — `data-itm-bulk-cancel="1"` buttons must be `type="button"`, not `type="submit"`.
3. **Row checkboxes** — `input[name="ids[]"]` with `form="bulk-delete-form"` (or matching form id); hidden until selection mode.
4. **Bound once** — JS sets `data-itm-bulk-delete-bound="1"` on the form after attach (safe to reload DOMContentLoaded).

**QA (`bulk_cancel` step, after `add`):** when bulk UI is visible, index HTML must include `bulk-delete-form`, `bulk-delete-selection.js`, `bulk_action`, and **Select to Delete** / `bulk_delete`; pass note lists what rendered in HTML plus shared JS contract. N/A when row count &lt; `records_per_page` (no bulk card).

#### Bulk UI row gate (`$perPage`) (mandatory)

After the tenant row count for the index list, resolve page size from UI config and gate bulk toolbar visibility:

```php
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$showBulkActions = ($totalRows >= $perPage);
```

| Check | Rule |
|--------|------|
| **Bulk toolbar** (`Select to Delete`, `Clear Table`, row `ids[]` checkboxes) | `$totalRows >= $perPage` |
| **Pagination footer** (`Previous` / `Next`) | `$totalRows > $perPage` |
| **Rejected (inverted)** | `$perPage >= $totalRows` or `if ($perPage >= $totalRows)` before bulk markup — hides bulk UI when QA expects `bulk_delete` / `clear_table` |

`scripts/check_ui_configuration_coverage.php` fails inverted gates. Module browser QA bulk-step skip behaviour: **`scripts/SCRIPTS.md`** (Full-module browser QA).

**Reference parents with inbound FKs:** when `inventory_items` (or other children) reference the module table without `ON DELETE CASCADE`, detach or clear child FK columns for the active `company_id` **before** `DELETE` on the parent (`inventory_categories`: `UPDATE inventory_items SET category_id = NULL …` then delete categories). Otherwise `bulk_delete`, `clear_table`, and `single_delete` can return HTTP 200 while rows remain and QA reports “still listed”.

#### List/search visible columns (`$uiColumns` / `$displayFieldColumns`) (mandatory)

Flattened CRUD `index.php` files build **`$uiColumns`** (visible list fields, often hiding `company_id` via `$hideCompanyIdTables`). List tables and sort use `$uiColumns`. **Search** must query the **same visible column set**. A common template mistake copied `foreach ($displayFieldColumns as $col)` in the search block without defining `$displayFieldColumns`, which logs `Undefined variable: displayFieldColumns` and `Invalid argument supplied for foreach()` when `?search=` is set (module browser QA **search** step exercises this on many modules).

**Mandatory contract:**

1. After **`$uiColumns`** is finalized (including any extra filters, e.g. `floor_plans` `list_all` column subset or `employee_onboarding_requests` list columns), assign **before** `$modulePath = dirname($_SERVER['PHP_SELF']);`:

```php
// Why: Search and list share visible columns; alias matches role/ui_configuration modules.
$displayFieldColumns = $uiColumns;
```

2. Modules that use **`$visibleFieldColumns`** for the list (e.g. `cable_colors`, `switch_ports`): `$displayFieldColumns = $visibleFieldColumns;` after that array is final.

3. Any **`foreach ($displayFieldColumns as $col)`** in the search SQL block requires the assignment above in the same request. Do not reference `$displayFieldColumns` without defining it.

4. List/view HTML may keep `foreach ($uiColumns as $col)` (or `$visibleFieldColumns`); search may use `$displayFieldColumns` or `$uiColumns` only **after** the alias line exists.

**Audit / repair** (see **`scripts/SCRIPTS.md`** and **`scripts/scripts.php`**):

| Script | When |
|--------|------|
| `php scripts/check_display_field_columns_search.php` | After changing flattened `index.php` search or list column variables; exit `1` if any index uses `$displayFieldColumns` without assignment |
| `php scripts/apply_display_field_columns_search_alias.php` | CLI-only maintenance to add the alias on modules missing it (idempotent; re-run when scaffolding new flattened modules) |

Not part of smoke — see **`scripts/SCRIPTS.md`** (Smoke tests). Bulk alias repair: `php scripts/apply_display_field_columns_search_alias.php`.

### 6. Empty-State Sample Data Process
* **UI:** Add "Add sample data" button at the bottom of `index.php` if the result set is empty for the active company.
* **Handler:** Implement a `POST` handler for `add_sample_data` in `index.php` that:
    * validates CSRF (`itm_require_post_csrf()`),
    * confirms there is an active `company_id`,
    * re-checks the table is empty for that `company_id` before inserting.
* **Source:** Seed rows must match `INSERT INTO` entries in `database.sql` for that module table.
* **Tenant Safety:** Always write seeded rows with active `company_id`; never expose/edit `company_id` in UI.

### 7. Module Consistency Guardrail (Mandatory)
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
  * **Windows Laragon:** use the full PHP binary path from **Setup & Debugging** below; script catalog, smoke, MBQA, and CLI conventions: **`scripts/SCRIPTS.md`**.
  * Minimum required checks for CRUD changes: `php -l` on touched PHP files and `php scripts/check_sql_injection_coverage.php` (use full PHP path on Windows Laragon).
  * When changing flattened `index.php` list/search column variables: `php scripts/check_display_field_columns_search.php` (see **List/search visible columns** above).
  * **PHPUnit suite / HTML coverage:** `php scripts/run_tests.php` or browser `scripts/run_tests.php?run=1&mode=coverage` — report at `phpunit/coverage/html/coverage.html` (Xdebug or PCOV). Authoring rules and coverage guardrails: **`scripts/SCRIPTS.md` → PHPUnit test runner**.
  * Optional broad QA, equipment/employees clear-table regression, and other script suites: **`scripts/SCRIPTS.md`**.
  * PR descriptions must list the exact commands that were run and their outcomes.
* **Branching and PRs:** Follow **NEW PR always** under **Change Hygiene → PR review (mandatory)** (fresh branch + new `gh pr create` per deliverable; do not reuse merged PRs for new scope).
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
      * **Windows Laragon fallback (when `php` is not on PATH):** `cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management` then `"C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\idfs_sync_human_test.php`
    * If any workflow or command run reports `[FAIL]`, the task is not complete.
* **Before commit, smoke-check all three screens at minimum:** list (`index.php`), detail (`view.php`), and edit (`edit.php`) for the changed module.
* **Wrapper action routing guardrail (mandatory):** for modules that use wrapper entry files (`create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php`) to set `$crud_action` before requiring `index.php`, verify `index.php` does not overwrite wrapper-provided values. Confirm each wrapper still routes to its expected screen/handler before creating a PR.
* **API:**
   * `scripts/api.php` needs to be updated if any changes on the project.
* **Implement the missing JSON import endpoint:**
   * For modules/*/index.php, so 📥Import Excel now handles table-tools.js save-to-database requests instead of falling through to normal page rendering (which caused the generic “Import failed while saving to database.” error).

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

### Directory Listing Prevention
* Every publicly accessible directory MUST contain an `index.php` or `index.html` file to prevent directory listing (unless listing is disabled via server configuration).
* **Every project folder:** every directory under the repository root **must** have an empty `index.html` (via `itm_upload_directory_empty_index_html()`). Run `php scripts/empty_folders.php` to backfill the full tree (skips `.git`, `.github`, and other dot directories).
* **Upload paths:** under `images/`, `tickets_photos/`, `floor_plans/`, `backups/`, and `files/`, `itm_ensure_upload_directory()` also force-writes managed `.htaccess`. Do not rely on a one-time manual `index.html` — helpers overwrite on each ensure so deleted placeholders are restored.

### Upload directory hardening (mandatory)
* **Never bare `mkdir()`** for application upload paths — use `itm_ensure_upload_directory()` or `itm_ensure_upload_directory_chain()` from `includes/bootstrap_helpers.php`.
* **Force-create on every folder (mandatory):** each helper call **overwrites** managed `.htaccess` (policy body) and an empty `index.html` (`itm_upload_directory_empty_index_html()`). Existing files are never skipped. Return value is false unless directory + both files exist.
* **Managed `.htaccess` policies (canonical):** bodies are defined in `includes/bootstrap_helpers.php` via `itm_upload_directory_policy_body($policy)` — **do not** hand-edit upload-tree `.htaccess` files; helpers overwrite them on every ensure. Human-readable reference with full Apache snippets: **`docs/file_upload_modules.md`**.

| Policy | Marker (first comment line) | Directories | HTTP effect |
|--------|----------------------------|-------------|-------------|
| `upload` | `ITM upload hardening` | `images/`, `tickets_photos/`, `floor_plans/` | Static assets allowed; PHP/script execution blocked |
| `deny_http` | `ITM files hardening` | `files/` and **every** segment under `files/{company_id}/…` | **All requests forbidden** (`RewriteRule ^ - [F]`); authorised access only via `modules/explorer/file.php` |
| `deny_all` | `ITM backup hardening` | `backups/` | All HTTP access denied (`Require all denied`) |

* **`upload` policy:** `images/`, `tickets_photos/`, `floor_plans/` — allow static files; disable PHP execution; force `.htaccess` + empty `index.html`.
* **`deny_http` policy:** `files/` and every segment under `files/{company_id}/…` — `RewriteEngine On` + `RewriteRule ^ - [F]` + `Options -Indexes -ExecCGI`; force `.htaccess` + empty `index.html` per segment; serve UI assets via `itm_files_serve_url()` → `modules/explorer/file.php`.
* **`deny_all` policy:** `backups/` — block all HTTP access; force `.htaccess` + empty `index.html`.
* **Backfill entire project:** `php scripts/empty_folders.php` — empty `index.html` on every folder under the repo root; upload paths also get managed `.htaccess`.
* **Backfill `files/` only:** `php scripts/ensure_files_htaccess_chain.php`. Full module map: `docs/file_upload_modules.md`.

## 🛡️ Safety & Side Effects
* **Risk of Regression (login.php):** Any changes to the login flow (e.g., joining with `user_roles`) must be carefully verified against the schema in `database.sql` to avoid breaking authentication for all users.
* **UI Redundancy:** Modules with custom export layouts should disable the default `table-tools.js` buttons using the `data-itm-no-export-*` attributes.

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
* **Booleans (List View):** Use badges for status (must NOT contain emojis):
    * `<span class="badge badge-success">Active</span>`
    * `<span class="badge badge-danger">Inactive</span>`
* **Booleans (Edit Mode):** Use icons: `1` = ✅, `0` = ❌.
* **Active Checkbox Guardrail (Double-Label Pattern):** In create/edit forms, treat `active` as a checkbox boolean for `tinyint` variants. Every form field must have a top-level `<label>`. For checkboxes, use the `itm-checkbox-control` wrapper with an inner `<span>` that repeats the humanized field name and includes the status indicator:
```html
<div class="form-group">
    <label><?= sanitize(cr_humanize_field('active')) ?></label>
    <label class="itm-checkbox-control">
        <input type="checkbox" name="active" value="1" <?= ($isActive ? 'checked' : '') ?>>
        <span><?= sanitize(cr_humanize_field('active')) ?> <span class="itm-check-indicator" aria-hidden="true"><?= ($isActive ? '✅' : '❌') ?></span></span>
    </label>
</div>
```
* **Strict prohibition:** HTML forms for the `active` database column must use the checkbox pattern above. The use of `<input type="text" name="active" value="1">` or `<input type="text" name="active" value="0">` is strictly forbidden.


* **Dynamic Selects:** Enable quick-add functionality: `<option value="__add_new__">➕</option>`.
* **Color Fields:** Use color picker UI: `<input type="color" name="hex_color" id="cable-hex-color-picker" value="#008000">`.
* **Date Fields:** Show date picker UI.
* **Switch Port Manager icon mapping (mandatory):** For `modules/equipment/index.php` generated switch port tiles, keep one centered icon with centered port number overlay. Map by port type + status (`Unknown` check is case-insensitive): RJ45 `Unknown` → `images/switch_port_icons/rj45_38x31_Unknown.png`, RJ45 non-`Unknown` → `images/switch_port_icons/rj45_38x31.png`, SFP `Unknown` → `images/switch_port_icons/sfp_38x38_Unknown.png`, SFP non-`Unknown` → `images/switch_port_icons/sfp_38x38.png`. When status is saved, refresh the icon immediately without page reload.

---

## 🛠 Setup & Debugging
* **Dev Credentials:** Host `localhost` | user `root` | **password `itmanagement`** | database `itmanagement` — same value in `config/config.php` (`DB_PASS`) and on the MySQL CLI (`-u root -pitmanagement`; the `-p` flag has **no space** before the password).
* **Windows Laragon portable — local paths (Nelson, verified):** When `php` / `mysql` are not on PATH, use these full absolute paths:

| What | Local path |
|------|------------|
| Laragon root | `C:\Users\NelsonSalvador\Downloads\laragon-portable` |
| ITM repository | `C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management` |
| App URL (Apache) | `http://localhost/it-management/` |
| phpMyAdmin (local) | `http://localhost/phpmyadmin/` |
| **PHP 7.4.33 (ITM — use this)** | `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe` |
| MySQL 8.4 CLI | `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |

### PHP CLI tests — full binary path (mandatory — Windows Laragon)

On **Windows Laragon** (Nelson's environment), **always** use the **full absolute path** to the PHP 7.4.33 binary when running PHP tests, audits, and `scripts/*.php` — do **not** rely on bare `php` on PATH. For script catalog, smoke, MBQA commands, and CLI conventions, see **`scripts/SCRIPTS.md`**.

| Rule | Detail |
|------|--------|
| **Shell** | Nelson's default terminal is **PowerShell** — prefix the quoted `php.exe` path with **`&`**. |
| **Run commands (PowerShell)** | From repo root: `& "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts/<script>.php [options]` |
| **Run commands (cmd.exe)** | `"C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe" scripts\<script>.php [options]` |
| **PR test plans / agent replies** | List the **exact full-path command** executed — not shortened `php scripts/...` |

**PowerShell error:** `Unexpected token 'scripts/...'` means the line is missing **`&`** before the quoted `php.exe` path.

On **Linux, macOS, CI, and any host where `php` is on PATH**, bare `php scripts/...` remains acceptable.

* **Import `database.sql` (full file — do not strip the first lines):**
    ```cmd
    cd /d C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management
    "C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -pitmanagement --default-character-set=utf8mb4 < database.sql
    ```
    Verify: `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='itmanagement';` → **88**, or `php scripts/verify_database_schema.php` (lists any missing tables). A partial import often stops at table **73** (`user_companies`) — missing block includes `role_hierarchy` … `workstation_ram`, `rack_planner`. Common deploy bugs: stripping the first lines of `database.sql` (removes `DROP DATABASE`), wrong MySQL password, or `re-download-replace_DB.ps1` piping without `-pitmanagement`. Use the updated `laragon-portable\www\re-download.ps1` (full file + table count). Capture stderr (`2> mysql-import.err`) — MySQL may exit 0 while statements failed.
  * **PowerShell piping:** `database.sql` in git is **LF**; `-split "`r`n"` can yield a single “line” and skip the strip branch — still import the **complete** file. Prefer `cmd /c "\"C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe\" -u root -pitmanagement --default-character-set=utf8mb4 < database.sql"` from the repo directory over stdin `Process` piping when imports truncate.
* **Online AI Test Environment:**
  * `https://nelsonsalvador.myddns.me` | Login: `Admin` | Password: `Admin`.
  * `http://nelsonsalvador.myddns.me/phpmyadmin/` | Database: `itmanagement` | Login: `root` | Password: (blank).
  * Note: `https://nelsonsalvador.myddns.me/phpmyadmin/` currently returns upstream TLS/certificate errors; use HTTP for phpMyAdmin checks.
* **Logs:** System errors are piped to `ROOT_PATH . 'error_log.txt'`.
* **Testing:** Browser screenshots are not supported; rely on verbose error logging. Script suites and full-module QA: **`scripts/SCRIPTS.md`**.
* **CLI scripts:** Run from the repository root with **PHP 7.4.33** and **MySQLi** enabled — conventions and catalog in **`scripts/SCRIPTS.md`**; Laragon binary path in **PHP CLI tests** above.

---

## 🎯 Root cause fixes (mandatory — no quick patches)
When a bug or review comment points at a symptom (wrong delete guard, flaky QA step, misleading label), **fix the underlying contract**, not the nearest line that makes the symptom go away.

* **Find the producer first:** identify what creates the bad state (for example `module_browser_qa_runner.php` random row tags, import Excel usernames, FK seed order). Read that code path before patching consumers.
* **One canonical marker:** test-only or disposable rows must use a **shared, documented signature** in one helper (for example `includes/itm_mbqa_test_user.php` for `MBQA-{table}-{company}-{seq}-{hash}`). Runner and modules must call the **same** builder and detector — do not duplicate regex/prefix logic.
* **Do not broaden security bypasses on untrusted data:** usernames, titles, emails, and free-text fields are normal application data. A short prefix match (`mbqa-`, `test-`, `qa-`) is **not** an acceptable root fix for delete guards or permission skips; use the strict runner/import signature or another trusted marker.
* **If the real fix is larger, say so in the PR** (schema flag, dedicated test role, runner cleanup hook) and implement the smallest **correct** contract change — not a permanent loose bypass.

---

## 🧹 Change Hygiene Rules (Diff Quality)
To keep PRs reviewable and avoid noisy churn, follow these rules for every change:
* **No line-ending normalization:** Preserve existing CRLF/LF style per file. Do not rewrite whole files just to change one line.
* **No broad search/replace across modules unless explicitly requested:** Prefer targeted edits to only the files required by the task.
* **Minimize touched lines:** Keep patches surgical and avoid formatting-only edits (spacing, wrapping, reindent) when logic is unchanged.
* **Preserve file encoding and structure:** Do not change charset, BOM behavior, or module layout unless requested. See **Character encoding, locales, and Unicode** — source files stay UTF-8 without BOM; only `qa-reports/*` JSON/MD may get a UTF-8 BOM from `itm_write_utf8_text_file()`.
* **If a change must be bulk-applied, state why in the PR description** and confirm the scope before continuing.

### PR review (mandatory)
* **Reviews and fixes** are done in-repo via Cursor, optional Bugbot, manual IDE review, and the scripts below — same intent as external P1/P2 bot comments.
* **FRESH PR only — never update an existing PR (mandatory — non-negotiable):**
  * **Every separate request, bugfix, follow-up, Codex/Bugbot fix, or AGENTS.md process update** ships on a **fresh branch** and opens a **brand-new pull request** with a **new PR number**. The user default is **never update an existing PR** for new work.
  * **`git push` is not a way to amend PR #N.** Allowed push pattern: **one** `git push -u origin <new-branch>` while publishing a branch that has **no PR yet**, then **`gh pr create`**. Any later change → **new branch** → **new push** → **new PR**.
  * **Do not push more commits to an open PR’s branch** — even for review fixes, CI fixes, or “small follow-ups”. If unsure, use a **fresh branch + new PR**.
  * Treat **“always a NEW PR”** and **“DON’T push to PR #N”** literally: **`gh pr create`** for each deliverable; the prior PR is history — **next change = next PR number**.
  * **Branch naming:** each deliverable gets a **new** `fix/…` or `feat/…` branch from current `origin/master` — do **not** reuse a branch name that already backs an open or merged PR for additional commits.
  * Package every requested implementation in that **fresh branch** and open that **new PR** when the work is ready—do **not** wait for an explicit “please commit” (unless the user asked to hold commits or the session is exploratory/read-only).
  * When required checks pass, **commit**, **`git push -u origin <new-branch>`**, and **`gh pr create`**. A task is not complete with only unstaged or unpushed local changes.
  * **NEVER commit only locally:** if `git status` shows `ahead 1` (or any “ahead of origin”), the deliverable is **incomplete** until push succeeds and a **new PR URL** (new number) is returned. Forbidden closing lines: “only committed locally”, “branch is ahead of origin by 1”, “push when ready”, “Pushed to an existing PR URL”, “updated the existing PR”.
  * **Do not ask for PR confirmation:** never end a deliverable with prompts like “say so and I will open a PR” or “would you like me to commit?” — **auto-open the fresh PR** when work is ready (same exceptions as **Agent compliance workflow** step 5).
  * **Completion checklist (same turn, before replying done):** `git checkout -b fix/new-unique-name` (from synced `master`) → commit → `git push -u origin HEAD` → **`gh pr create`** → reply with the **new PR URL** (must be a URL the user has not been given before for this deliverable). Do **not** treat `gh pr view` on an old PR as completion.
  * Do not reuse a previously opened **pull request** for a **new** request, even if the files overlap.
  * **Review replies vs code:** posting **`Fixed`** on an old PR thread is allowed; **code** for that fix still lands on a **fresh branch + new PR** unless the user explicitly said to amend the open PR.
  * Preferred status wording example: “I’m now packaging this as a fresh branch/PR (per your **NEW PR always** rule) with the root sync fixes, the human-flow regression test, and the AGENTS guardrail update.”
* **Avoid GitHub “We couldn’t merge this pull request” errors:**
  * **Always branch from current `origin/master`** before new work — never stack new commits on a branch whose PR already merged (GitHub may show merge errors or stale diffs).
  * **Required sync (repository root):**
    ```bash
    git fetch origin master
    git checkout master
    git pull origin master
    git checkout -b fix/short-descriptive-name
    ```
  * After merge of PR #N, **do not push** to that branch again; the next task uses a **new branch name**, **one new first-publish push**, and **`gh pr create`** (new PR number).
  * **Never `git push` to a branch that already backs a PR** — cherry-pick or re-apply the patch onto a **new branch from `master`** instead.
  * If GitHub reports merge conflicts on an open PR, **close** it (or leave it) and open a **fresh PR** from a clean branch off current `master` — do **not** force-push to “fix” the old PR unless the user **explicitly** asked to keep and update **that same PR number**.
  * Before `gh pr create`, confirm `git log origin/master..HEAD` contains only commits for the current deliverable.
  * **PR body encoding check:** use `gh pr create --body-file` (see **PR descriptions: shell / quoting corruption** under Character encoding). After create/edit, run `gh pr view <number> --json body` or open the PR on GitHub — if step names or `$variables` look corrupted, fix with `gh pr edit --body-file`; that is shell quoting, not a repo UTF-8 issue.
* **Pre-push diff review (mandatory — before the one allowed `git push -u` on a new branch):**
  * **Scope:** applies only to the **first publish** of a **new** branch that will become a **new** PR. It does **not** authorize a second push to an existing PR branch.
  * **Read the full diff, not the commit message alone.** A one-line commit can still delete dozens of lines (regression example: a “routing fix” branch that removed FK detach helpers while merging after a correct PR had already landed on `master`).
  * **Required commands (run and inspect output before push):**
    ```bash
    git fetch origin master
    git diff --stat origin/master...HEAD
    git diff origin/master...HEAD
    git log --oneline origin/master..HEAD
    ```
  * **Hard stop — do not run the first-publish push / do not open a PR** when any of these apply unless the user explicitly requested that scope:
    * **Unexpected large deletions** in touched files (e.g. whole `function …` blocks, delete handlers, or helpers removed while the task was “add one attribute” or “fix routing”).
    * **`git diff --stat` shows many more deletions than additions** for a small fix — re-check for a bad `git reset`, wrong merge resolution, or editing the wrong file revision.
    * **The branch would revert code already on `origin/master`** (diff re-introduces removed bugs or strips a just-merged PR).
    * **Two PRs for the same deliverable** — if the correct fix is already on a branch or merged, **abandon** the stale branch (do not merge a second PR). Cherry-pick or recreate from current `master` instead.
  * **After a mistaken first-publish push:** create a **new branch from current `origin/master`**, re-apply only the intended patch, verify diff again, then **one new first-publish push** and **one new PR** — never add commits to the bad branch and push again.
  * **Completion gate:** “ready for first-publish push” means you can summarize every deleted/changed hunk in plain language **and** the branch has **no existing PR**; if you cannot, read the diff again. Deliverable done = **new PR URL**, not “pushed to …/pull/N”.
* **Pre-merge review pass (required before merge):** on every PR, run a targeted review of the changed files (last N files in the diff when large) against this `AGENTS.md`, including at minimum:
  * `php -l` on every touched `.php` file.
  * `php scripts/check_sql_injection_coverage.php` when PHP/SQL changed.
  * `php scripts/check_audit_logs_coverage.php` when CRUD or audit-related paths changed.
  * `php scripts/check_database_sql_company_name_uniques.php` when `database.sql` or tenant unique keys changed.
  * FK label guardrails: no raw `*_id` / `*_by` numeric IDs on list/detail when a label exists; persisted FKs stay selected on edit forms.
  * Module consistency rechecks for any touched module (`index.php`, `view.php`, `edit.php`, `create.php`, `list_all.php`, and `delete.php` when applicable).
  * IDF-related changes: `php scripts/idfs_sync_human_test.php` (or `C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\idfs_sync_human_test.php` from the repo root) — hard-fail if any `[FAIL]`.
  * Smoke/CI: see **`scripts/SCRIPTS.md`** (Smoke tests) when `.github/workflows/smoke.yml` applies; list exact commands and outcomes in the PR description (do not claim “no tests run” when checks ran).
* **CI and repo scripts stay authoritative:** the smoke workflow must pass on PRs; see **`scripts/SCRIPTS.md`** and **`scripts/scripts.php`** for other maintenance scripts to run when the change scope requires them.

### GitHub PR review comments (mandatory)
* **Read all GitHub PR feedback** before considering a PR merge-ready: use `gh pr view`, `gh api repos/{owner}/{repo}/pulls/{number}/comments`, GraphQL review-thread endpoints, or the PR URL. Include human reviewers, **Bugbot**, **Codex**, and actionable CI/check annotations when present.
* **One actionable comment → one fresh Cursor chat:** for each distinct review comment or coherent thread that requests a change, **start a new forked/isolated agent chat** scoped to that item only. Do not mix unrelated review threads in the same session unless they share one root cause.
* **Implement on a fresh branch + new PR:** address the comment with code/docs changes per **NEW PR always** (see **PR review** above). Link the **new** resolving PR in the GitHub reply when applicable — do **not** only push commits to the PR that received the comment unless the user explicitly asked to update that PR.
* **Auto-fix when asked to address review comments:** for each actionable thread, (1) verify whether `master` already contains the fix, (2) if not, patch on a **fresh branch + new PR**, (3) run the relevant checks (`php -l` on touched files, plus any script named in the comment or PR scope), (4) post the GitHub reply below. Do not assume silence means done.
* **Always reply on GitHub with a status label:** every addressed review thread must receive an explicit reply that **starts with exactly** **`Fixed`** or **`Not Fixed`** (that spelling and capitalization). Do **not** use `Fix`, `fix`, `Won't fix`, or other variants—the label must be searchable and consistent.
  * **`Fixed`:** the concern is resolved in a merged commit or an open linked PR; state what changed (file, commit/PR number, or command run).
  * **`Not Fixed`:** intentional deferral, out of scope, or blocked; state why and cite a follow-up issue/PR if planned.
* **Do not leave actionable review threads silent:** if a comment asked for a change, respond with **`Fixed`** or **`Not Fixed`**—never only push code without a labeled GitHub reply.
* **Merged PRs still need replies:** if a PR merged with silent bot/human threads, post retroactive **`Fixed`** / **`Not Fixed`** replies on each thread after verifying `master` (or cite the PR that already fixed it).
* **Passwords Module Security:** All password-related data must be strictly scoped to `user_id` and encrypted at rest using `itm_encrypt` with the user's master key stored in `$_SESSION['vault_key']`.
* **Doc updates for process rules:** agent-workflow and repo-wide rules → **`AGENTS.md`**; scripts directory standards → **`scripts/SCRIPTS.md`**. Ship each on a fresh branch + new PR (do not fold into an unrelated feature PR).

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
5. **After merge:** add a short follow-up reply on the same thread, e.g. **`Fixed`** — merged to `master`, when the fix landed in a follow-up PR.

**Reply templates (searchable labels):**
* **`Fixed`** — merged to `master`; `scripts/check_ui_configuration_coverage.php` now rejects inverted `perPage >= totalRows` gates.
* **`Fixed`** — on `master`; `modules/system_access/index.php` tbody `ids[]` cells gated with `$showBulkActions`.
* **`Not Fixed`** — intentional per `scripts/SCRIPTS.md` (phpMyAdmin linked only from `scripts/scripts.php`, not derived per-request host).

## 📚 API Examples
The `api-examples/` directory contains standalone PHP scripts that demonstrate how to interact with the system programmatically. These scripts are intended as a reference for developers and for automated integration testing.

- **Bulk Imports:** Examples for Equipment, Employees, Tickets, Catalogs, and Events using the `import_excel_rows` JSON payload.
- **Authentication:** Helper scripts for capturing session cookies and CSRF tokens from the login process.
- **CRUD & Filtering:** Examples for archiving tickets, deleting records, viewing single items, and filtering list views (e.g., "Open" tickets).

When adding new API features or complex handlers, always consider adding a corresponding example in `api-examples/` and update `scripts/api.php` to include it in the documentation.

## Cursor Cloud specific instructions

Cloud Agent VMs run Ubuntu 24.04 and do not ship with PHP, MySQL, or Apache pre-installed. The update script (run automatically on VM startup) installs them via `apt-get`; agents do **not** need to repeat those steps.

### Services — how to start after VM boot

| Service | Start command | Verify |
|---------|---------------|--------|
| **MySQL 8.0** | `sudo mkdir -p /var/run/mysqld && sudo chown mysql:mysql /var/run/mysqld && sudo chmod 755 /var/run/mysqld && sudo mysqld --user=mysql --datadir=/var/lib/mysql &` then `sleep 5` | `mysqladmin -u root -pitmanagement ping` → `mysqld is alive` |
| **Apache 2.4** | `sudo apachectl start` | `curl -s -o /dev/null -w '%{http_code}' http://localhost/it-management/login.php` → `200` |

MySQL root password is `itmanagement` (set by the update script on first run). Re-import the schema after a fresh VM with `mysql -u root -pitmanagement --default-character-set=utf8mb4 < database.sql` and verify 88 tables: `mysql -u root -pitmanagement -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='itmanagement';"`.

### Apache alias

The update script creates `/etc/apache2/conf-available/it-management.conf` which aliases `/it-management` to `/workspace`. This matches the `.htaccess` `RewriteBase /it-management/`.

### Smoke tests

See **`scripts/SCRIPTS.md`** (Smoke tests). On Cloud Agent VMs:

```bash
bash scripts/smoke_test.sh
```


### Bypassing Login (Dev/Test)

For rapid development or testing, you can bypass the login screen and pre-authenticate as an Admin:

1. Run the bypass script via CLI:
   ```bash
   php scripts/bypass_login.php
   ```
2. The script will output a `Session ID`.
3. Open the app in your browser (e.g., `http://localhost/it-management/`).
4. Open Browser DevTools -> Application -> Cookies.
5. Replace the value of `PHPSESSID` with the `Session ID` from step 2.
6. Refresh the page to be logged in as Admin with Company 1 (TechCorp Global) selected and the Passwords Vault unlocked.

### App login

Default credentials: username `Admin`, password `Admin`. After login, select a company (e.g. TechCorp Global) to access modules.

### Gotchas

* MySQL `dpkg --configure` may hang on first install because systemd service start is blocked in the container. The update script works around this by starting `mysqld` directly and setting the root password manually.
* The `/var/run/mysqld/` directory permissions default to `drwx------` (mysql:mysql) which prevents non-root socket access. The startup sequence above includes `chmod 755` to fix this.
* PHP 7.4 is not the system default on Ubuntu 24.04; `update-alternatives --set php /usr/bin/php7.4` is run by the update script so `php` resolves to 7.4.33.
