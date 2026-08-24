# AGENT_NOTES.md - Problem Ticket Links

## 1. Module Purpose

Junction table linking **problems** to incident **tickets** (many tickets per problem). Distinct from ticket merge; managed from Problem Management and this flattened CRUD module.

## 2. Key Tables

- **problem_ticket_links** — `company_id`, `problem_id`, `ticket_id`, `linked_at`, `linked_by`, standard audit columns.

## 3. Required Relationships

- **problem_ticket_links** → **problems** (`problem_id`, `ON DELETE CASCADE`)
- **problem_ticket_links** → **tickets** (`ticket_id`, `ON DELETE CASCADE`)
- **problem_ticket_links** → **employees** (`linked_by`, `ON DELETE SET NULL`)

## 4. Business Rules (Critical for Agents)

- Unique per tenant: `(company_id, problem_id, ticket_id)`.
- Prefer linking via `includes/itm_problem_management.php` from the problems/tickets UI; this CRUD module is for inspection/admin.
- `company_id` hidden in list/view/forms; stamped server-side on save.

## 5. UI Behavior Requirements

- Hide `company_id` via `$hideCompanyIdTables` (includes `problem_ticket_links`).
- **Add sample data:** requires parent rows — `itm_sample_data_prerequisite_map()` seeds `problems` and `tickets` first (`problems` parents: `knowledge_base`, `master_tickets`). Template in `db/02_data_sample.sql` lists `problems` before `problem_ticket_links`.

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php`.

## 7. File Structure

- **index.php** — list, view, edit, import; wrappers `edit.php`, `view.php`, `list_all.php` require this file.
- **create.php** — standalone create entry.
- **delete.php** — soft-delete handler.

## 8. Multi-Tenant Rules

- All queries filter by session `company_id`.

## 9. Audit Logging Requirements

- `trg_problem_ticket_links_audit_insert|update|delete` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Do not seed `problem_ticket_links` before `problems` — FK `problem_id` will fail (sample SQL order + prerequisite map).
- Problems sample row uses `master_ticket_id = NULL` so seed works when no `master_tickets` row exists yet.

## 11. Examples of Safe Code Patterns

```php
itm_problem_link_ticket($conn, $companyId, $problemId, $ticketId, $employeeId);
```

## 12. Module Owner Notes (Optional)

- Canonical doc: `docs/PROBLEM_MANAGEMENT.md`, `modules/problems/AGENT_NOTES.md`.
- Regression: `php scripts/verify_problem_management.php`.
