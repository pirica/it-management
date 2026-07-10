\# AGENT\_NOTES.md - Email Alert Rules



\## 1. Module Purpose

Email Alert Rules manages per-company automated notification settings for the Email Management area. Rules control whether expiry and reminder alerts are enabled, how many days before an event they fire, and which recipient emails receive them.



\## 2. Key Tables

\- \*\*email\_alert\_rules\*\* — per-company alert toggles (`rule\_slug`, `enabled`, `days\_before`, `notify\_emails`, `active`).



\## 3. Required Relationships

\- \*\*email\_alert\_rules\*\* → \*\*companies\*\* via `company\_id` (`ON DELETE CASCADE`).

\- Alert dispatch is consumed by `scripts/run\_email\_alert\_rules.php` and the parent Email Management module.



\## 4. Business Rules (Critical for Agents)

\- This module is not in the Protection Zone.

\- Email Alert Rules is a sub-feature of \*\*Email Management\*\*. Server-side CRUD permission checks must use the parent module slug \*\*`emails`\*\*, not `email\_alert\_rules`, because `database.sql` registers Email Management in `modules\_registry` as `emails`.

\- Rules are tenant-scoped by `company\_id`; do not expose or edit `company\_id` in the UI.

\- `rule\_slug` identifies the automation rule and should remain stable for the alert runner.



\## 5. UI Behavior Requirements

\- Standard flattened CRUD in `index.php` with wrappers (`create.php`, `edit.php`, `delete.php`, `view.php`, `list\_all.php`).

\- Search, sort, pagination, bulk delete, import/export controls, and Actions-cell markers follow the standard CRUD pattern.

\- Forms use `cr\_require\_valid\_csrf\_token()` for POST handling and include the shared CSRF token.

\- Email Management also has a bespoke parent UI in `modules/emails/`; keep copy and behaviour aligned with the Alert Rules tab there.



\## 6. API Actions (If Applicable)

\- \*\*import\_excel\_rows\*\* — JSON POST on `index.php` through the standard CRUD import flow.



\## 7. File Structure

\- \*\*index.php\*\* — list, create, edit, view, delete, bulk actions, and import handler depending on `crud\_action`.

\- \*\*create.php\*\* — wrapper to `index.php` create flow.

\- \*\*edit.php\*\* — wrapper to `index.php` edit flow.

\- \*\*delete.php\*\* — wrapper to `index.php` delete flow.

\- \*\*view.php\*\* — wrapper to `index.php` detail flow.

\- \*\*list\_all.php\*\* — wrapper to `index.php` list-all flow.



\## 8. Multi-Tenant Rules

\- All row reads and writes are scoped by the active session `company\_id`.

\- `company\_id` is hidden from list, detail, and form views.



\## 9. Audit Logging Requirements

\- Database triggers `trg\_email\_alert\_rules\_audit\_insert`, `trg\_email\_alert\_rules\_audit\_update`, and `trg\_email\_alert\_rules\_audit\_delete` write to `audit\_logs` on DML.

\- Actor context comes from `@app\_employee\_id`, `@app\_company\_id`, and related session variables set by `config/config.php`.



\## 10. Common Pitfalls

\- Do not change CRUD RBAC to `$crud\_table` for this module; use the parent `emails` permission scope so Email Management role permissions continue to cover Alert Rules.

\- Do not document a standalone sidebar permission unless `modules\_registry` is intentionally changed to add `email\_alert\_rules`.

\- Do not remove `rule\_slug` values used by scheduled alert runners without updating the runner logic and seeds.



\## 11. Examples of Safe Code Patterns



\### Safe SELECT

```php

$stmt = $conn->prepare('SELECT \* FROM email\_alert\_rules WHERE company\_id = ? AND id = ?');

$stmt->bind\_param('ii', $companyId, $id);

$stmt->execute();

```



\### Safe UPDATE

```php

$stmt = $conn->prepare('UPDATE email\_alert\_rules SET enabled = ?, days\_before = ?, notify\_emails = ? WHERE company\_id = ? AND id = ?');

$stmt->bind\_param('iisii', $enabled, $daysBefore, $notifyEmails, $companyId, $id);

$stmt->execute();

```



\## 12. Module Owner Notes (Optional)

Parent module notes: `modules/emails/AGENT\_NOTES.md`. Regression: `php scripts/verify\_emails\_module.php` when DB services are available.
