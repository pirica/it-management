Read AGENTS.md
Read README.md
Read scripts/scripts.php
Read phpunit/*
Read scripts/api.php
Read scripts/SCRIPTS.md
Read split database files (database/01_schema.sql, database/02_triggers.sql, database/03_data.sql)
Read full project

On base on your learnings edit/update or create AGENT_NOTES.md is none exists for each modules/ on base of this (Module Template) don't use scripts to auto the process.
`templates/AGENT_NOTES.md` (Module Template)


# AGENT_NOTES.md — module template

Use this outline for every in-scope folder (`modules/<slug>/`, `config/`, `includes/`, `scripts/lib/`, etc.). **Read the module PHP and `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` first** — do not bulk-generate or copy generic boilerplate without verifying behaviour.

File title: `# AGENT_NOTES.md - <Human Name>`

---

## 1. Module Purpose

Briefly describe what this module does and why it exists.

Example: This module manages workstation assets, including OS version, RAM, office location, and assignment history.

---

## 2. Key Tables

List only tables this module owns or primarily interacts with.

Format:

- **table_name** — purpose

Example:

- **workstations** — main workstation records
- **workstation_ram** — lookup table for RAM sizes

---

## 3. Required Relationships

Document foreign keys and cross-module dependencies.

Format:

- **this_table** → depends on **other_table** (`fk_column`, `ON DELETE` behaviour)
- **this_table** → referenced by **child_table**

Example:

- Workstations link to employees via `employee_id`.
- Workstations link to equipment when a workstation is also an asset.

---

## 4. Business Rules (Critical for Agents)

Rules that must never be violated. Document module-specific constraints from `AGENTS.md` when applicable.

Examples:

- A workstation cannot be assigned to an inactive employee.
- OS version must exist in `workstation_os_versions`.
- Deleting a workstation must archive assignment history, not remove it.

---

## 5. UI Behavior Requirements

Document UI constraints agents must preserve. Match **actual** module code — not a generic CRUD checklist.

### Flattened CRUD (`modules/<slug>/index.php` with `$crud_table`)

Typical contract (verify per module):

- Search, sort, server-side pagination (`records_per_page`)
- Bulk delete when `$totalRows >= $perPage` (not inverted)
- `$displayFieldColumns = $uiColumns` before search block when search uses `$displayFieldColumns`
- Hide `company_id` from list/view/forms
- Actions column: `class="itm-actions-cell"` and `data-itm-actions-origin="1"`
- Import: `data-itm-db-import-endpoint="index.php"` on the table that handles `import_excel_rows` (may be `list_all.php` on bespoke modules)
- **CSRF:** POST handlers use **`cr_require_valid_csrf_token()`** (local helper in manufacturers-style CRUD); forms include `csrf_token` from `itm_get_csrf_token()`. Do **not** document `itm_require_post_csrf()` unless that helper is actually called in this module's PHP.
- **`active` checkbox:** double-label `itm-checkbox-control` pattern (`AGENTS.md`)

### `is_*` equipment façades

- **List/view:** type filter via `$equipmentTypeNameFilter` in wrapper `index.php` / `view.php`
- **Edit:** wrapper `edit.php` often `require`s `equipment/edit.php` **without** the type filter — document as a **known gap** if true; do not claim edit is type-guarded unless code enforces it
- JSON/import handlers run through façade `index.php` when it `require`s `equipment/index.php`

### Bespoke / read-only modules

Describe real screens (e.g. calendar aggregation, resignations report, explorer ACL). Call out exceptions (e.g. calendar ICS import writes to `events`).

---

## 6. API Actions (If Applicable)

Document endpoints this module exposes.

Format:

- **action_name** — purpose, required params, response format

Examples:

- **import_excel_rows** — JSON POST on `index.php` (flattened CRUD)
- **ajax_inline_edit** — POST on bespoke `index.php` with CSRF

Use `None` or `N/A` when the module has no API surface.

---

## 7. File Structure

List files and their purpose.

Example:

- **index.php** — list view
- **create.php** — create form
- **edit.php** — update form
- **delete.php** — delete handler
- **view.php** — detail view
- **list_all.php** — alternate list wrapper

---

## 8. Multi-Tenant Rules

Document scoping beyond generic `company_id`.

Examples:

- All queries filter by `company_id` from session.
- Private data also filters by `employee_id` — **only document if code actually does this**.
- Child `ops_report_id` rows: FK does not always enforce parent `company_id` match — note if application must validate.

---

## 9. Audit Logging Requirements

Describe what is logged and how.

### Database triggers (most CRUD tables)

- Name triggers: `trg_{table}_audit_insert|update|delete` in `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`
- Triggers **always** insert into `audit_logs` on DML — they are **not** gated by the `enable_audit_logs` UI setting
- Actor context: `@app_employee_id`, `@app_company_id` from `config/config.php`

### Application / read-only modules

- State explicitly when no writes occur (e.g. resignations report)

Do **not** write “when `enable_audit_logs` is enabled” for standard DB trigger tables unless PHP explicitly checks that flag before DML.

---

## 10. Common Pitfalls

Mistakes agents must avoid. Verify FK delete behaviour in `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`:

| Child FK | Pitfall text |
|----------|----------------|
| `ON DELETE SET NULL` | Child FKs null out automatically — no manual detach |
| `ON DELETE CASCADE` | Parent delete removes children |
| No CASCADE / no SET NULL | Detach or clear child FKs for active `company_id` **before** parent delete |

Other examples:

- Do not delete rows still referenced when schema blocks delete.
- Do not copy generic “detach first” text without checking `information_schema` / `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`.
- Bespoke or sensitive modules: change only when explicitly requested.
- Document **known gaps** (missing `employee_id` filter, unguarded edit URLs) rather than ideal behaviour.

---

## 11. Examples of Safe Code Patterns

Provide 1–2 examples using **real table and column names** from `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql`.

### Safe SELECT

```php
$stmt = $conn->prepare('SELECT * FROM example_table WHERE company_id = ? AND id = ?');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
```

### Safe INSERT

```php
$stmt = $conn->prepare('INSERT INTO example_table (company_id, name) VALUES (?, ?)');
$stmt->bind_param('is', $companyId, $name);
$stmt->execute();
```

Rules:

- Use MySQLi prepared statements only — never concatenate user input into SQL
- For `IN (...)` lists, use placeholder expansion (`str_repeat('i', count($ids))`), not `implode(',', $ids)` in the query string

---

## 12. Module Owner Notes (Optional)

Regression scripts, related `AGENT_NOTES.md` files, or follow-up hardening (document only — do not cite numbered PRs).

Example: Regression: `php scripts/verify_<module>.php`. Parent module: `modules/ops_report/AGENT_NOTES.md`.

---

## Authoring checklist (before marking complete)

1. Read module entry PHP (`index.php` minimum; wrappers for `is_*`).
2. Grep `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` for `CREATE TABLE` and `trg_{table}_audit_*`.
3. Confirm CSRF helper name in PHP matches section 5.
4. Confirm audit section matches unconditional triggers (unless module is read-only).
5. Confirm section 11 column names exist in schema.
6. Update parent folder `AGENT_NOTES.md` when editing a subfolder.
